<?php
require_once __DIR__.'/app/controller.php';
require_once __DIR__.'/app/views.php';
require_once __DIR__.'/app/extra_views.php';

$action=(string)($_GET['action']??'');
$module=(string)($_GET['module']??'');
try {
    if($action==='setup' && $_SERVER['REQUEST_METHOD']==='POST') {
        verify_csrf();
        if(setup_complete()) {flash('warning','Setup has already been completed.');redirect(['action'=>'login']);}
        $name=trim((string)($_POST['full_name']??''));$email=strtolower(trim((string)($_POST['email']??'')));
        $password=(string)($_POST['password']??'');$uni=(int)($_POST['university_id']??0);
        if(mb_strlen($name)<2 || mb_strlen($name)>160 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12 ||
            !preg_match('/[A-Za-z]/',$password) || !preg_match('/\d/',$password) || !preg_match('/[^A-Za-z0-9]/',$password) ||
            !hash_equals($password,(string)($_POST['confirm_password']??''))) {
            $_SESSION['errors']=['Enter a valid name and email. Password must be 12+ characters and contain letters, a number, and a symbol.'];redirect(['action'=>'setup']);
        }
        $lock=(int)db()->query("SELECT GET_LOCK('college_erp_initial_setup',5)")->fetchColumn();
        if(!$lock || setup_complete()) {if($lock)db()->query("SELECT RELEASE_LOCK('college_erp_initial_setup')");flash('warning','Setup is already complete.');redirect(['action'=>'login']);}
        try {
            $q=db()->prepare('SELECT id FROM universities WHERE id=?');$q->execute([$uni]);if(!$q->fetchColumn())throw new RuntimeException('Choose an existing university.');
            db()->beginTransaction();
            $ins=db()->prepare("INSERT INTO users(university_id,full_name,email,password_hash,role,active) VALUES(?,?,?,?,'super_admin',1)");
            $ins->execute([$uni,$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
            db()->commit();flash('success','Super Admin created. Sign in to continue.');
        } catch(Throwable $e) {if(db()->inTransaction())db()->rollBack();throw $e;}
        finally {db()->query("SELECT RELEASE_LOCK('college_erp_initial_setup')");}
        redirect(['action'=>'login']);
    }
    if($action==='setup') {setup_view();exit;}
    if($action==='login' && $_SERVER['REQUEST_METHOD']==='POST') {
        verify_csrf();
        if(!setup_complete()) redirect(['action'=>'setup']);
        $email=strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['password']??'');
        $q=db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');$q->execute([$email]);$person=$q->fetch();
        $locked=$person && $person['locked_until'] && strtotime($person['locked_until'])>time();
        if(!$person || !$person['active'] || $locked || !password_verify($password,$person['password_hash'])) {
            if($person && !$locked) {
                $fail=(int)$person['failed_logins']+1;
                $lockUntil=$fail>=5?date('Y-m-d H:i:s',time()+900):null;
                $up=db()->prepare('UPDATE users SET failed_logins=?,locked_until=? WHERE id=?');$up->execute([$fail,$lockUntil,$person['id']]);
            }
            usleep(250000);flash('danger','Sign in failed. Check your credentials or contact your administrator.');redirect(['action'=>'login']);
        }
        $portal=(string)($_POST['portal']??'');
        $portalRoles=[
            'admin'=>['super_admin','university_admin','college_admin'],
            'faculty'=>['teacher'],
            'student'=>['student','parent'],
        ];
        if($portal!=='' && (!isset($portalRoles[$portal]) || !in_array($person['role'],$portalRoles[$portal],true))) {
            flash('danger','The selected portal does not match this account. Choose the correct portal and try again.');
            redirect(['action'=>'login']);
        }
        db()->prepare('UPDATE users SET failed_logins=0,locked_until=NULL,last_login=NOW() WHERE id=?')->execute([$person['id']]);
        $studentId=null;
        if($person['role']==='student') {$s=db()->prepare('SELECT id FROM students WHERE user_id=?');$s->execute([$person['id']]);$studentId=$s->fetchColumn();}
        session_regenerate_id(true);
        $_SESSION['user']=['id'=>(int)$person['id'],'full_name'=>$person['full_name'],'email'=>$person['email'],'role'=>$person['role'],
            'university_id'=>$person['university_id']?(int)$person['university_id']:null,'college_id'=>$person['college_id']?(int)$person['college_id']:null,
            'student_id'=>$studentId?(int)$studentId:null];
        $collegeName=null;
        if($person['college_id']){$c=db()->prepare('SELECT name FROM colleges WHERE id=?');$c->execute([$person['college_id']]);$collegeName=$c->fetchColumn();}
        $_SESSION['user']['college_name']=$collegeName;
        redirect();
    }
    if($action==='login') {login_view();exit;}
    if($action==='logout') {
        if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required.');}
        require_login();verify_csrf();$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>'Lax']);}
        session_destroy();redirect(['action'=>'login']);
    }
    if(!current_user()) {if(!setup_complete()) redirect(['action'=>'setup']);redirect(['action'=>'login']);}
    if($action==='enroll' && $_SERVER['REQUEST_METHOD']==='POST'){create_enrollment((int)($_POST['id']??0));}
    if($action==='receipt') {receipt_view((int)($_GET['id']??0));exit;}
    if($action==='reports'){reports_view();exit;}
    if($action==='accounts' && $_SERVER['REQUEST_METHOD']==='POST') {
        require_login();if(!is_admin()){http_response_code(403);exit('Access denied.');}verify_csrf();
        $u=current_user();$do=$_POST['do']??'';
        if($do==='reset_password') {
            $id=(int)($_POST['id']??0);
            $new=(string)($_POST['new_password']??'');
            $confirmation=(string)($_POST['confirm_password']??'');
            if($id<1 || $id===(int)$u['id']) {
                flash('danger','Choose another account. You can change your own password from the account menu.');
                redirect(['action'=>'accounts']);
            }
            if(strlen($new)<12 || !preg_match('/[A-Za-z]/',$new) || !preg_match('/\d/',$new) ||
                !preg_match('/[^A-Za-z0-9]/',$new) || !hash_equals($new,$confirmation)) {
                $_SESSION['errors']=['The new password must be at least 12 characters with letters, a number, and a symbol; both entries must match.'];
                redirect(['action'=>'accounts']);
            }
            if($u['role']==='super_admin') {
                $targetQuery=db()->prepare('SELECT id,role FROM users WHERE id=? AND role<>?');
                $targetQuery->execute([$id,'super_admin']);
            } elseif($u['role']==='university_admin') {
                $targetQuery=db()->prepare('SELECT id,role FROM users WHERE id=? AND university_id=? AND role<>?');
                $targetQuery->execute([$id,$u['university_id'],'super_admin']);
            } else {
                $targetQuery=db()->prepare('SELECT id,role FROM users WHERE id=? AND college_id=? AND role<>?');
                $targetQuery->execute([$id,$u['college_id'],'super_admin']);
            }
            $target=$targetQuery->fetch();
            if(!$target) {
                flash('danger','Account not found or outside your permitted scope.');
                redirect(['action'=>'accounts']);
            }
            db()->prepare('UPDATE users SET password_hash=?,failed_logins=0,locked_until=NULL WHERE id=?')
                ->execute([password_hash($new,PASSWORD_DEFAULT),$id]);
            flash('success','Password reset. Share the new password with the account holder through a private channel.');
            redirect(['action'=>'accounts']);
        }
        if($do==='toggle') {
            $id=(int)($_POST['id']??0);
            if($id===$u['id']){flash('danger','You cannot deactivate your own account.');redirect(['action'=>'accounts']);}
            if($u['role']==='super_admin'){$q=db()->prepare("SELECT id,role FROM users WHERE id=?");$q->execute([$id]);}
            elseif($u['role']==='university_admin'){$q=db()->prepare("SELECT id,role FROM users WHERE id=? AND university_id=?");$q->execute([$id,$u['university_id']]);}
            else {$q=db()->prepare("SELECT id,role FROM users WHERE id=? AND college_id=?");$q->execute([$id,$u['college_id']]);}
            $target=$q->fetch();
            if(!$target || $target['role']==='super_admin'){flash('danger','Account not found or not manageable.');redirect(['action'=>'accounts']);}
            db()->prepare('UPDATE users SET active=1-active WHERE id=?')->execute([$id]);flash('success','Account status updated.');redirect(['action'=>'accounts']);
        }
        if($do==='create') {
            $name=trim((string)($_POST['full_name']??''));$email=strtolower(trim((string)($_POST['email']??'')));$pass=(string)($_POST['password']??'');
            $role=(string)($_POST['role']??'');$validRoles=$u['role']==='super_admin'?['university_admin','college_admin','teacher','student','parent']:($u['role']==='university_admin'?['college_admin','teacher','student','parent']:['teacher','student','parent']);
            $collegeId=(int)($_POST['college_id']??0);$studentId=(int)($_POST['student_id']??0);
            $staffId=(int)($_POST['staff_id']??0);
            if(mb_strlen($name)<2 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($pass)<12 || !preg_match('/[A-Za-z]/',$pass)||!preg_match('/\d/',$pass)||!preg_match('/[^A-Za-z0-9]/',$pass)||!hash_equals($pass,(string)($_POST['confirm_password']??''))||!in_array($role,$validRoles,true)) {
                $_SESSION['errors']=['Enter valid account details and a 12+ character password containing letters, numbers, and a symbol.'];redirect(['action'=>'accounts']);
            }
            if($role==='university_admin') {
                $collegeId=0;$uniId=(int)($u['university_id']??0);
                if($u['role']==='super_admin') {
                    $uniId=(int)($_POST['account_university_id']??0);$uq=db()->prepare('SELECT id FROM universities WHERE id=?');$uq->execute([$uniId]);
                    if(!$uq->fetchColumn()){flash('danger','Choose an existing university for this University Admin account.');redirect(['action'=>'accounts']);}
                }
            } else {
                if($u['role']==='super_admin') {$cq=db()->prepare('SELECT id,university_id FROM colleges WHERE id=?');$cq->execute([$collegeId]);}
                elseif($u['role']==='university_admin') {$cq=db()->prepare('SELECT id,university_id FROM colleges WHERE id=? AND university_id=?');$cq->execute([$collegeId,$u['university_id']]);}
                else {$cq=db()->prepare('SELECT id,university_id FROM colleges WHERE id=? AND id=?');$cq->execute([$collegeId,$u['college_id']]);}
                $college=$cq->fetch();if(!$college){flash('danger','Choose an accessible college.');redirect(['action'=>'accounts']);}$uniId=(int)$college['university_id'];
            }
            if($studentId) {
                if($u['role']==='super_admin') $sq=db()->prepare('SELECT id,college_id,user_id FROM students WHERE id=?');
                elseif($u['role']==='university_admin') $sq=db()->prepare('SELECT id,college_id,user_id FROM students WHERE id=? AND university_id=?');
                else $sq=db()->prepare('SELECT id,college_id,user_id FROM students WHERE id=? AND college_id=?');
                $args=[$studentId];if($u['role']==='university_admin')$args[]=$u['university_id'];elseif($u['role']==='college_admin')$args[]=$u['college_id'];$sq->execute($args);$student=$sq->fetch();
                if(!$student || ($role==='student' && ($student['college_id']!=$collegeId || !empty($student['user_id'])))){flash('danger','Selected student is outside your permitted scope or already linked.');redirect(['action'=>'accounts']);}
            }
            if($role==='student' && !$studentId){flash('danger','Student account must link to a student record.');redirect(['action'=>'accounts']);}
            if($role==='teacher' && !$staffId){flash('danger','Teacher account must link to a staff record.');redirect(['action'=>'accounts']);}
            if($staffId) {
                if($u['role']==='super_admin')$tq=db()->prepare('SELECT id,college_id,user_id FROM staff WHERE id=?');
                elseif($u['role']==='university_admin')$tq=db()->prepare('SELECT id,college_id,user_id FROM staff WHERE id=? AND university_id=?');
                else $tq=db()->prepare('SELECT id,college_id,user_id FROM staff WHERE id=? AND college_id=?');
                $targs=[$staffId];if($u['role']==='university_admin')$targs[]=$u['university_id'];elseif($u['role']==='college_admin')$targs[]=$u['college_id'];$tq->execute($targs);$staffPerson=$tq->fetch();
                if(!$staffPerson || $staffPerson['college_id']!=$collegeId || ($staffPerson['user_id']!==null && $staffPerson['user_id']!=='')){flash('danger','Selected staff record is already linked or outside the selected college.');redirect(['action'=>'accounts']);}
            }
            try {
                db()->beginTransaction();
                $ins=db()->prepare('INSERT INTO users(university_id,college_id,full_name,email,password_hash,role,active) VALUES(?,?,?,?,?,?,1)');
                $ins->execute([$uniId,$collegeId?:null,$name,$email,password_hash($pass,PASSWORD_DEFAULT),$role]);$newId=(int)db()->lastInsertId();
                if($role==='student' && $studentId) db()->prepare('UPDATE students SET user_id=? WHERE id=? AND (user_id IS NULL OR user_id=?)')->execute([$newId,$studentId,$newId]);
                if($role==='teacher' && $staffId) db()->prepare('UPDATE staff SET user_id=? WHERE id=? AND user_id IS NULL')->execute([$newId,$staffId]);
                if($role==='parent') {
                    $submitted=$_POST['parent_student_ids']??[];if(!is_array($submitted))$submitted=[$submitted];
                    $ids=array_unique(array_filter(array_map('intval',$submitted)));
                    if(!$ids || count($ids)>50)throw new RuntimeException('Choose between one and fifty linked students for a Parent account.');
                    foreach($ids as $sid) {
                        $check=db()->prepare('SELECT id FROM students WHERE id=? AND college_id=?');$check->execute([$sid,$collegeId]);if(!$check->fetchColumn())throw new RuntimeException('Linked student outside selected college.');
                        db()->prepare('INSERT INTO parent_students(parent_user_id,student_id) VALUES(?,?)')->execute([$newId,$sid]);
                    }
                }
                db()->commit();flash('success','Account created. Provide the password securely to the account holder.');
            } catch(Throwable $e){if(db()->inTransaction())db()->rollBack();flash('danger','Could not create account. Email may already be in use, or a student is already linked.');}
            redirect(['action'=>'accounts']);
        }
        flash('danger','Unknown action.');redirect(['action'=>'accounts']);
    }
    if($action==='accounts'){accounts_view();exit;}
    if($action==='password' && $_SERVER['REQUEST_METHOD']==='POST') {
        require_login();verify_csrf();$u=current_user();
        $q=db()->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$u['id']]);$hash=$q->fetchColumn();
        $new=(string)($_POST['new_password']??'');
        if(!password_verify((string)($_POST['old_password']??''),(string)$hash)||strlen($new)<12||!preg_match('/[A-Za-z]/',$new)||!preg_match('/\d/',$new)||!preg_match('/[^A-Za-z0-9]/',$new)) {
            $_SESSION['errors']=['Current password is incorrect or the new password does not meet the 12-character complexity requirement.'];redirect(['action'=>'password']);
        }
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$u['id']]);flash('success','Password updated.');redirect();
    }
    if($action==='password') {
        begin_page('Change password');echo '<div class="card auth-card"><div class="card-body"><h1 class="h3">Change password</h1><form method="post" action="'.e(url(['action'=>'password'])).'">'.csrf_field().'<label class="form-label">Current password</label><input class="form-control mb-3" type="password" name="old_password" required><label class="form-label">New password</label><input class="form-control mb-3" type="password" name="new_password" minlength="12" required><button class="btn btn-primary">Update password</button></form></div></div>';end_page();exit;
    }
    if($module!=='') {
        $m=modules()[$module]??null;if(!$m){http_response_code(404);exit('Module not found.');}
        if($_SERVER['REQUEST_METHOD']==='POST'){handle_module_post($module,$m);}
        module_view($module);exit;
    }
    dashboard_view();
} catch(PDOException $e) {
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>Database not ready</title><link rel="stylesheet" href="public/assets/bootstrap.min.css"><main class="container py-5"><h1 class="h3">Database connection unavailable</h1><p>Import <code>database/schema.sql</code>, create the configured MySQL database, and copy <code>config/config.example.php</code> to <code>config/config.php</code> with local settings.</p><p class="text-muted">The application does not expose connection credentials or raw SQL errors.</p></main>';
} catch(Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Request could not be completed</title><link rel="stylesheet" href="public/assets/bootstrap.min.css"><main class="container py-5"><h1 class="h3">Request could not be completed</h1><p>Please verify the submitted data and database setup, then try again. No internal error details are shown.</p></main>';
}

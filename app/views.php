<?php
function begin_page(string $title): void {
    $u=current_user(); $flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
    $nav=[];
    foreach(modules() as $k=>$m) if(allowed_module($k)) $nav[$k]=$m['title'];
    $navIcons=['universities'=>'⌂','colleges'=>'▦','admissions'=>'▣','students'=>'♙','staff'=>'♟','academic_years'=>'◷','classes'=>'▤','sections'=>'☷','subjects'=>'▧','teacher_assignments'=>'⇄','attendance'=>'✓','fee_types'=>'₹','fee_charges'=>'₹','payments'=>'₹','exams'=>'▤','exam_marks'=>'★','books'=>'▣','library_loans'=>'↻','hostels'=>'⌂','hostel_rooms'=>'▦','hostel_allocations'=>'▤','transport_routes'=>'↗','transport_vehicles'=>'▰','transport_assignments'=>'↔'];
    $role=$u?role_name($u['role']):'';
    ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=e($title)?> · Campus ERP</title><link rel="stylesheet" href="public/assets/bootstrap.min.css">
<link rel="stylesheet" href="public/assets/app.css"></head><body>
<?php if($u): ?><div class="app-layout">
<aside class="sidebar">
<a class="brand-lockup" href="<?=e(url())?>"><span class="brand-mark" aria-hidden="true">✦</span><span class="brand-copy"><strong>College ERP System</strong><small>Smart management for a better future</small></span></a>
<nav class="side-nav" aria-label="Main navigation">
<span class="nav-caption">Workspace</span>
<a class="nav-link<?=!isset($_GET['module'])&&!isset($_GET['action'])?' active':''?>" href="<?=e(url())?>"><span class="nav-icon" aria-hidden="true">⌂</span>Dashboard</a>
<?php foreach($nav as $k=>$label):?><a class="nav-link<?=($_GET['module']??'')===$k?' active':''?>" href="<?=e(url(['module'=>$k]))?>"><span class="nav-icon" aria-hidden="true"><?=e($navIcons[$k]??'•')?></span><?=e($label)?></a><?php endforeach;?>
<?php if(is_admin()):?><span class="nav-caption nav-caption-secondary">Administration</span><a class="nav-link<?=($_GET['action']??'')==='accounts'?' active':''?>" href="<?=e(url(['action'=>'accounts']))?>"><span class="nav-icon" aria-hidden="true">◎</span>User accounts</a><a class="nav-link<?=($_GET['action']??'')==='reports'?' active':''?>" href="<?=e(url(['action'=>'reports']))?>"><span class="nav-icon" aria-hidden="true">▤</span>Reports</a><?php endif;?>
</nav>
<div class="sidebar-footer"><span class="footer-mark" aria-hidden="true">✦</span><span>College ERP<small>Campus management</small></span></div>
</aside>
<div class="app-main">
<header class="topbar"><div class="topbar-context"><span class="topbar-date"><?=e(date('l, d F Y'))?></span><span class="topbar-divider" aria-hidden="true"></span><span><?=e($role)?><?=!empty($u['college_name'])?' · '.e($u['college_name']):''?></span></div><div class="topbar-account"><span class="account-avatar" aria-hidden="true"><?=e(mb_strtoupper(mb_substr($u['full_name'],0,1)))?></span><span class="account-copy"><strong><?=e($u['full_name'])?></strong><small><?=e($role)?></small></span><a class="topbar-action" href="<?=e(url(['action'=>'password']))?>">Change password</a><form method="post" action="<?=e(url(['action'=>'logout']))?>"><?=csrf_field()?><button class="topbar-action signout-button">Sign out</button></form></div></header>
<main class="page-content">
<?php else: ?><main class="<?=$title==='Sign in'?'auth-page':'container py-5 auth-wrap'?>"><?php endif;
    if($flash): ?><div class="alert alert-<?=e($flash[0])?> alert-dismissible fade show"><?=e($flash[1])?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;
    if(!empty($_SESSION['errors'])) { echo '<div class="alert alert-danger"><ul class="mb-0">'; foreach($_SESSION['errors'] as $er) echo '<li>'.e($er).'</li>'; echo '</ul></div>'; unset($_SESSION['errors']); }
}
function end_page(): void { ?></main><?php if(current_user()) echo '</div></div>'; ?><script src="public/assets/bootstrap.bundle.min.js"></script><script src="public/assets/app.js"></script></body></html><?php }
function dashboard_view(): void {
    $u=require_login(); begin_page('Dashboard');
    echo '<section class="welcome-banner"><div><span class="eyebrow">CAMPUS OVERVIEW</span><h1>Welcome back, '.e($u['full_name']).'!</h1><p>Here’s what’s happening across your campus today.</p><span class="welcome-date">▦ &nbsp;'.e(date('l, d F Y')).'</span></div><div class="welcome-art" aria-hidden="true"><span class="art-orbit orbit-one"></span><span class="art-orbit orbit-two"></span><span class="art-cap">✦</span></div></section>';
    if(in_array($u['role'],['student','parent'],true)) {
        echo '<div class="row g-3 dashboard-metrics">';
        foreach(['students'=>'Student profile','attendance'=>'Attendance','fee_charges'=>'Fee charges','payments'=>'Receipts','exam_marks'=>'Results','library_loans'=>'Library loans'] as $k=>$title) {
            if(!allowed_module($k)) continue;
            $n=count(list_records($k,modules()[$k])); echo '<div class="col-sm-6 col-xl-4"><a class="dashboard-stat" href="'.e(url(['module'=>$k])).'"><span class="stat-icon">'.e(mb_substr($title,0,1)).'</span><span class="stat-copy"><small>'.e($title).'</small><strong>'.e((string)$n).'</strong><em>View details <span aria-hidden="true">→</span></em></span></a></div>';
        }
        echo '</div><div class="dashboard-note"><span aria-hidden="true">ⓘ</span><span>Your access is limited to the student record(s) linked to this account. Exam results are shown only after publication.</span></div>';
    } else {
        $metrics=[
            ['Students','students','students','student','blue'],
            ['Faculty & staff','staff','staff','users','violet'],
            ['Courses','subjects','subjects','book','green'],
            ['Fees collected','payments','payments','currency','orange'],
        ];
        echo '<div class="row g-3 dashboard-metrics">';
        foreach($metrics as [$label,$module,$table,$icon,$tone]) {
            if(!allowed_module($module)) continue;
            [$where,$params]=module_scope($module,modules()[$module]);
            $sql=$icon==='currency'?'SELECT COALESCE(SUM(t.amount),0) FROM payments t'.$where:"SELECT COUNT(*) FROM `{$table}` t".$where;
            $query=db()->prepare($sql);$query->execute($params);$value=$query->fetchColumn();
            $display=$icon==='currency'?'₹ '.number_format((float)$value,2):number_format((int)$value);
            $symbol=$icon==='currency'?'₹':($icon==='student'?'♙':($icon==='users'?'♟':'▤'));
            echo '<div class="col-sm-6 col-xl-3"><a class="dashboard-stat" href="'.e(url(['module'=>$module])).'"><span class="stat-icon tone-'.e($tone).'" aria-hidden="true">'.e($symbol).'</span><span class="stat-copy"><small>'.e($label).'</small><strong>'.e($display).'</strong><em>View records <span aria-hidden="true">→</span></em></span></a></div>';
        }
        echo '</div>';

        $actions=[
            ['Add student','students','＋'],['Add faculty','staff','♟'],['Mark attendance','attendance','✓'],
            ['Record fees','payments','₹'],['Examinations','exams','▤'],['Generate report','reports','▧']
        ];
        echo '<section class="dashboard-panel quick-panel mt-4"><div class="panel-heading"><div><span class="eyebrow">GET THINGS DONE</span><h2>Quick actions</h2></div></div><div class="quick-actions">';
        foreach($actions as [$label,$key,$icon]) {
            $href=$key==='reports'?url(['action'=>'reports']):url(['module'=>$key]);
            $allowed=$key==='reports'?is_admin():allowed_module($key);
            if($allowed) echo '<a class="quick-action" href="'.e($href).'"><span class="quick-icon">'.e($icon).'</span><span>'.e($label).'</span><span class="quick-arrow" aria-hidden="true">→</span></a>';
        }
        echo '</div></section>';

        echo '<div class="row g-4 dashboard-lower">';
        if(allowed_module('students')) {
            [$w,$p]=module_scope('students',modules()['students']);
            $query=db()->prepare('SELECT DATE_FORMAT(t.created_at,\'%Y-%m\') month_key,COUNT(*) total FROM students t'.$w.($w?' AND ':' WHERE ').'t.created_at>=DATE_SUB(CURDATE(),INTERVAL 5 MONTH) GROUP BY month_key ORDER BY month_key');$query->execute($p);
            $counts=[];foreach($query->fetchAll() as $r)$counts[$r['month_key']]=(int)$r['total'];
            $months=[];for($i=5;$i>=0;$i--){$date=new DateTimeImmutable('first day of this month');$date=$date->modify("-{$i} months");$key=$date->format('Y-m');$months[]=['label'=>$date->format('M'),'value'=>$counts[$key]??0];}
            $max=max(1,max(array_column($months,'value')));
            echo '<section class="col-xl-7"><div class="dashboard-panel chart-panel"><div class="panel-heading"><div><span class="eyebrow">ENROLLMENT</span><h2>Student registrations</h2></div><a href="'.e(url(['module'=>'students'])).'">View students <span aria-hidden="true">→</span></a></div><div class="enrollment-chart" role="img" aria-label="Student registrations for the last six months">';
            foreach($months as $month){$height=$month['value']>0?max(8,(int)round($month['value']/$max*100)):3;echo '<div class="chart-column"><span class="chart-value">'.number_format($month['value']).'</span><span class="chart-bar" style="height:'.$height.'%"></span><span class="chart-label">'.e($month['label']).'</span></div>';}
            echo '</div><div class="chart-legend"><span><i></i> New student records · last 6 months</span></div></div></section>';
        }

        if(allowed_module('attendance')) {
            [$w,$p]=module_scope('attendance',modules()['attendance']);
            $query=db()->prepare('SELECT t.status,COUNT(*) total FROM attendance t'.$w.($w?' AND ':' WHERE ').'t.attendance_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY t.status');$query->execute($p);
            $attendance=['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];foreach($query->fetchAll() as $r)$attendance[$r['status']]=(int)$r['total'];
            $attendanceTotal=array_sum($attendance);$presentPct=$attendanceTotal?(int)round($attendance['present']/$attendanceTotal*100):0;
            echo '<section class="col-xl-5"><div class="dashboard-panel attendance-panel"><div class="panel-heading"><div><span class="eyebrow">LAST 30 DAYS</span><h2>Attendance overview</h2></div><a href="'.e(url(['module'=>'attendance'])).'">Details <span aria-hidden="true">→</span></a></div>';
            if($attendanceTotal){$a=100*$attendance['present']/$attendanceTotal;$b=$a+100*$attendance['absent']/$attendanceTotal;$c=$b+100*$attendance['late']/$attendanceTotal;
                echo '<div class="attendance-content"><div class="attendance-donut" style="--present:'.$a.'%;--absent:'.$b.'%;--late:'.$c.'%"><div><strong>'.$presentPct.'%</strong><small>Present</small></div></div><div class="attendance-legend"><span><i class="legend-present"></i>Present <b>'.number_format($attendance['present']).'</b></span><span><i class="legend-absent"></i>Absent <b>'.number_format($attendance['absent']).'</b></span><span><i class="legend-late"></i>Late <b>'.number_format($attendance['late']).'</b></span><span><i class="legend-excused"></i>Excused <b>'.number_format($attendance['excused']).'</b></span></div></div>';
            } else echo '<div class="dashboard-empty">No attendance records were found in the last 30 days.</div>';
            echo '</div></section>';
        }

        if(allowed_module('admissions')) {
            [$w,$p]=module_scope('admissions',modules()['admissions']);$query=db()->prepare('SELECT t.applicant_name,t.program,t.status,t.application_date FROM admissions t'.$w.' ORDER BY t.application_date DESC,t.id DESC LIMIT 5');$query->execute($p);$rows=$query->fetchAll();
            echo '<section class="col-xl-6"><div class="dashboard-panel data-panel"><div class="panel-heading"><div><span class="eyebrow">ADMISSIONS</span><h2>Recent applications</h2></div><a href="'.e(url(['module'=>'admissions'])).'">View all <span aria-hidden="true">→</span></a></div>';
            if($rows){echo '<div class="table-responsive"><table class="table dashboard-table mb-0"><thead><tr><th>Applicant</th><th>Program</th><th>Status</th><th>Date</th></tr></thead><tbody>';foreach($rows as $r)echo '<tr><td>'.e($r['applicant_name']).'</td><td>'.e($r['program']).'</td><td><span class="status-pill">'.e(ucwords(str_replace('_',' ',$r['status']))).'</span></td><td>'.e(date('d M Y',strtotime($r['application_date']))).'</td></tr>';echo '</tbody></table></div>';}else echo '<div class="dashboard-empty">No admission applications have been recorded.</div>';
            echo '</div></section>';
        }

        if(allowed_module('exams')) {
            [$w,$p]=module_scope('exams',modules()['exams']);$query=db()->prepare('SELECT t.name,t.class_name,t.exam_date,t.published FROM exams t'.$w.($w?' AND ':' WHERE ').'t.exam_date>=CURDATE() ORDER BY t.exam_date ASC LIMIT 5');$query->execute($p);$rows=$query->fetchAll();
            echo '<section class="col-xl-6"><div class="dashboard-panel data-panel"><div class="panel-heading"><div><span class="eyebrow">ACADEMICS</span><h2>Upcoming examinations</h2></div><a href="'.e(url(['module'=>'exams'])).'">View all <span aria-hidden="true">→</span></a></div>';
            if($rows){echo '<div class="table-responsive"><table class="table dashboard-table mb-0"><thead><tr><th>Examination</th><th>Class</th><th>Date</th><th>Status</th></tr></thead><tbody>';foreach($rows as $r)echo '<tr><td>'.e($r['name']).'</td><td>'.e($r['class_name']).'</td><td>'.e(date('d M Y',strtotime($r['exam_date']))).'</td><td><span class="status-pill">'.((int)$r['published']?'Published':'Scheduled').'</span></td></tr>';echo '</tbody></table></div>';}else echo '<div class="dashboard-empty">No upcoming examinations have been scheduled.</div>';
            echo '</div></section>';
        }

        if(allowed_module('payments')&&allowed_module('fee_charges')) {
            [$pw,$pp]=module_scope('payments',modules()['payments']);[$cw,$cp]=module_scope('fee_charges',modules()['fee_charges']);
            $query=db()->prepare('SELECT COALESCE(SUM(t.amount),0) total FROM payments t'.$pw);$query->execute($pp);$paid=(float)$query->fetchColumn();
            $query=db()->prepare('SELECT COALESCE(SUM(t.amount),0) total FROM fee_charges t'.$cw);$query->execute($cp);$billed=(float)$query->fetchColumn();
            $ratio=$billed>0?min(100,(int)round($paid/$billed*100)):0;
            echo '<section class="col-xl-6"><div class="dashboard-panel finance-panel"><div class="panel-heading"><div><span class="eyebrow">FINANCE</span><h2>Fee collection status</h2></div><a href="'.e(url(['module'=>'payments'])).'">View receipts <span aria-hidden="true">→</span></a></div><div class="finance-summary"><div class="finance-gauge" style="--collection:'.$ratio.'%"><span>'.$ratio.'%</span></div><div class="finance-copy"><span>Collected</span><strong>₹ '.number_format($paid,2).' <small>/ ₹ '.number_format($billed,2).'</small></strong><div class="finance-progress"><span style="width:'.$ratio.'%"></span></div><small>Based on recorded charges and payments</small></div></div></div></section>';
        }

        if(allowed_module('students')) {
            [$w,$p]=module_scope('students',modules()['students']);$query=db()->prepare("SELECT COALESCE(NULLIF(t.class_name,''),'Not assigned') class_name,COUNT(*) total FROM students t".$w." GROUP BY class_name ORDER BY total DESC,class_name ASC LIMIT 5");$query->execute($p);$rows=$query->fetchAll();
            echo '<section class="col-xl-6"><div class="dashboard-panel data-panel"><div class="panel-heading"><div><span class="eyebrow">STUDENT BODY</span><h2>Students by class</h2></div><a href="'.e(url(['module'=>'students'])).'">View roster <span aria-hidden="true">→</span></a></div>';
            if($rows){echo '<ol class="class-list">';foreach($rows as $i=>$r)echo '<li><span class="class-rank">'.($i+1).'</span><span>'.e($r['class_name']).'</span><strong>'.number_format((int)$r['total']).'</strong></li>';echo '</ol>';}else echo '<div class="dashboard-empty">No student records are available yet.</div>';
            echo '</div></section>';
        }
        echo '</div><footer class="dashboard-footer">College ERP · Live information from your authorized college records.</footer>';
    }
    end_page();
}
function module_view(string $key): void {
    $m=require_module($key); $records=list_records($key,$m); $canWrite=module_can_write($key);
    begin_page($m['title']); $editId=(int)($_GET['edit']??0); $editing=$editId?get_record($key,$m,$editId):null;
    echo '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h3">'.e($m['title']).'</h1><div class="text-muted small">Tenant-scoped records · '.count($records).' shown (maximum 300)</div></div>';
    if($canWrite) echo '<a class="btn btn-primary" href="'.e(url(['module'=>$key,'edit'=>0])).'#record-form">＋ Add record</a>';
    echo '</div>';
    if($key==='exam_marks' && in_array(current_user()['role'],['student','parent'],true)) {
        [$rw,$rp]=module_scope($key,$m);
        $rw.=($rw?' AND ':' WHERE ').'t.exam_id IN (SELECT id FROM exams WHERE published=1)';
        $q=db()->prepare("SELECT t.exam_id,t.student_id,SUM(t.marks) total_marks,SUM(t.max_marks) total_max FROM exam_marks t{$rw} GROUP BY t.exam_id,t.student_id ORDER BY t.exam_id DESC LIMIT 300");
        $q->execute($rp);$totals=$q->fetchAll();
        echo '<section class="card mb-4"><div class="card-header fw-semibold">Published exam totals</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Exam</th><th>Student</th><th>Total</th><th>Percentage</th><th>Overall grade</th></tr></thead><tbody>';
        foreach($totals as $total) {
            $q=db()->prepare('SELECT name FROM exams WHERE id=?');$q->execute([$total['exam_id']]);$examName=$q->fetchColumn()?:'—';
            $q=db()->prepare('SELECT full_name FROM students WHERE id=?');$q->execute([$total['student_id']]);$studentName=$q->fetchColumn()?:'—';
            $pct=(float)$total['total_max']>0?100*(float)$total['total_marks']/(float)$total['total_max']:0;
            $grade=$pct>=90?'A':($pct>=80?'B':($pct>=70?'C':($pct>=60?'D':'F')));
            echo '<tr><td>'.e($examName).'</td><td>'.e($studentName).'</td><td>'.number_format((float)$total['total_marks'],2).' / '.number_format((float)$total['total_max'],2).'</td><td>'.number_format($pct,1).'%</td><td><span class="badge text-bg-primary">'.e($grade).'</span></td></tr>';
        }
        if(!$totals) echo '<tr><td colspan="5" class="text-muted">No published results yet.</td></tr>';
        echo '</tbody></table></div><div class="card-body small text-muted">Overall grade is calculated from total marks: A ≥ 90%, B ≥ 80%, C ≥ 70%, D ≥ 60%, otherwise F.</div></section>';
    }
    if($canWrite) {
        $old=$_SESSION['form_old']??[]; unset($_SESSION['form_old']);
        echo '<section class="card mb-4" id="record-form"><div class="card-header fw-semibold">'.($editing?'Edit record #'.e((string)$editId):'Add new record').'</div><div class="card-body"><form method="post" action="'.e(url(['module'=>$key])).'" class="row g-3">'.csrf_field().'<input type="hidden" name="do" value="save"><input type="hidden" name="id" value="'.e((string)$editId).'">';
        foreach($m['fields'] as $name=>$meta) {
            [$label,$type,$required]=$meta; $value=$old[$name]??($editing[$name]??'');
            echo '<div class="col-md-6"><label class="form-label" for="f_'.e($name).'">'.e($label).($required?' <span class="text-danger">*</span>':'').'</label>';
            if(str_starts_with($type,'select:')) {
                echo '<select class="form-select" name="'.e($name).'" id="f_'.e($name).'" '.($required?'required':'').'><option value="">Choose…</option>';
                foreach(explode(',',substr($type,7)) as $opt) echo '<option value="'.e($opt).'"'.((string)$value===$opt?' selected':'').'>'.e(ucfirst(str_replace('_',' ',$opt))).'</option>';
                echo '</select>';
            } elseif(str_starts_with($type,'ref:')) {
                echo '<select class="form-select" name="'.e($name).'" id="f_'.e($name).'" '.($required?'required':'').'><option value="">Choose…</option>';
                foreach(ref_options($type) as $opt) echo '<option value="'.e((string)$opt['id']).'"'.((string)$value===(string)$opt['id']?' selected':'').'>'.e($opt['label']).'</option>';
                echo '</select>';
            } elseif($type==='textarea') echo '<textarea class="form-control" name="'.e($name).'" id="f_'.e($name).'" rows="2" '.($required?'required':'').'>'.e((string)$value).'</textarea>';
            elseif($type==='yesno') echo '<select class="form-select" name="'.e($name).'" id="f_'.e($name).'" required><option value="1"'.((string)$value==='1'?' selected':'').'>Yes</option><option value="0"'.((string)$value==='0'?' selected':'').'>No</option></select>';
            else { $inputType=in_array($type,['date','email','number','decimal','integer'],true)?($type==='decimal'||$type==='integer'?'number':$type):'text'; $step=$type==='decimal'?' step="0.01" min="0"':($type==='integer'?' step="1" min="0"':''); echo '<input class="form-control" type="'.e($inputType).'" '.$step.' name="'.e($name).'" id="f_'.e($name).'" value="'.e((string)$value).'" '.($required?'required':'').'>'; }
            echo '</div>';
        }
        // Super Admin chooses a tenant explicitly; all other users get server-assigned scope.
        $u=current_user(); $table=$m['table']; $cols=db()->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        if(in_array($u['role'],['super_admin','university_admin'],true) && in_array('college_id',$cols,true) && $key!=='colleges' && !$editing) {
            if($u['role']==='super_admin') $cs=db()->query('SELECT id,name FROM colleges ORDER BY name')->fetchAll();
            else {$cq=db()->prepare('SELECT id,name FROM colleges WHERE university_id=? ORDER BY name');$cq->execute([$u['university_id']]);$cs=$cq->fetchAll();}
            echo '<div class="col-md-6"><label class="form-label">College *</label><select class="form-select" name="college_id" required><option value="">Choose college…</option>';
            foreach($cs as $c) echo '<option value="'.e((string)$c['id']).'"'.((string)($old['college_id']??($editing['college_id']??''))===(string)$c['id']?' selected':'').'>'.e($c['name']).'</option>';
            echo '</select></div>';
        }
        if($editing && in_array($u['role'],['super_admin','university_admin'],true) && in_array('college_id',$cols,true) && $key!=='colleges') {
            $cq=db()->prepare('SELECT name FROM colleges WHERE id=?');$cq->execute([(int)$editing['college_id']]);
            echo '<div class="col-md-6"><label class="form-label">College</label><div class="form-control-plaintext">'.e($cq->fetchColumn()?:'—').' <span class="text-muted">(tenant is fixed for this record)</span></div></div>';
        }
        if($u['role']==='super_admin' && $key==='colleges' && !$editing) {
            $us=db()->query('SELECT id,name FROM universities ORDER BY name')->fetchAll();
            echo '<div class="col-md-6"><label class="form-label">University *</label><select class="form-select" name="university_id" required><option value="">Choose university…</option>';
            foreach($us as $v) echo '<option value="'.e((string)$v['id']).'"'.((string)($old['university_id']??($editing['university_id']??''))===(string)$v['id']?' selected':'').'>'.e($v['name']).'</option>';
            echo '</select></div>';
        }
        if($editing && $u['role']==='super_admin' && $key==='colleges') {
            $uq=db()->prepare('SELECT name FROM universities WHERE id=?');$uq->execute([(int)$editing['university_id']]);
            echo '<div class="col-md-6"><label class="form-label">University</label><div class="form-control-plaintext">'.e($uq->fetchColumn()?:'—').' <span class="text-muted">(tenant is fixed for this record)</span></div></div>';
        }
        echo '<div class="col-12"><button class="btn btn-primary">Save record</button> <a class="btn btn-outline-secondary" href="'.e(url(['module'=>$key])).'">Cancel</a></div></form></div></section>';
    }
    $visibleFields=array_keys($m['fields']);
    echo '<div class="table-responsive card"><table class="table table-hover align-middle mb-0"><thead><tr><th>ID</th>';
    foreach($visibleFields as $f) echo '<th>'.e($m['fields'][$f][0]).'</th>';
    if($key==='books') echo '<th>Available</th>';
    if($key==='exam_marks') echo '<th>Calculated grade</th>';
    if($key==='exam_marks') echo '<th>Maximum marks</th>';
    if($key==='payments') echo '<th>Receipt</th>';
    if($key==='payments') echo '<th>Receipt no.</th>';
    if($key==='fee_charges') echo '<th>Paid / balance</th>';
    if($key==='library_loans') echo '<th>Due status</th>';
    echo '<th>Actions</th></tr></thead><tbody>';
    foreach($records as $r) {
        echo '<tr><td>'.e((string)$r['id']).'</td>';
        foreach($visibleFields as $f) {
            $v=$r[$f]??'';
            if(str_starts_with($m['fields'][$f][1],'ref:') && $v!=='') {
                [, $spec]=explode(':',$m['fields'][$f][1],2); [$rt,$rf]=explode(':',$spec,2);
                $q=db()->prepare("SELECT `$rf` FROM `$rt` WHERE id=?");$q->execute([(int)$v]);$v=$q->fetchColumn()?:'—';
            }
            echo '<td>'.e((string)$v).'</td>';
        }
        if($key==='books') {
            $q=db()->prepare("SELECT COUNT(*) FROM library_loans WHERE book_id=? AND status='issued'");$q->execute([$r['id']]);
            echo '<td>'.max(0,(int)$r['copies']-(int)$q->fetchColumn()).' / '.e((string)$r['copies']).'</td>';
        }
        if($key==='exam_marks') echo '<td><span class="badge text-bg-secondary">'.e((string)($r['grade']??'—')).'</span></td>';
        if($key==='exam_marks') echo '<td>'.number_format((float)$r['max_marks'],2).'</td>';
        if($key==='payments') echo '<td><a target="_blank" rel="noopener noreferrer" href="'.e(url(['action'=>'receipt','id'=>$r['id']])).'">Print receipt</a></td><td>'.e((string)$r['receipt_no']).'</td>';
        if($key==='fee_charges') { $q=db()->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE charge_id=?');$q->execute([$r['id']]);$paid=(float)$q->fetchColumn(); echo '<td>'.number_format($paid,2).' paid / '.number_format((float)$r['amount']-$paid,2).' due</td>'; }
        if($key==='library_loans') {
            $overdue=$r['status']==='issued' && $r['due_on']<date('Y-m-d');
            echo '<td>'.($overdue?'<span class="text-danger fw-semibold">Overdue</span>':'<span class="text-muted">—</span>').'</td>';
        }
        echo '<td class="text-nowrap">';
        if($canWrite) {
            echo '<a class="btn btn-sm btn-outline-primary me-1" href="'.e(url(['module'=>$key,'edit'=>$r['id']])).'#record-form">Edit</a>';
            echo '<form class="d-inline" method="post" action="'.e(url(['module'=>$key])).'" onsubmit="return confirm(\'Delete this record? This cannot be undone.\')">'.csrf_field().'<input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="'.e((string)$r['id']).'"><button class="btn btn-sm btn-outline-danger">Delete</button></form>';
            if($key==='admissions' && in_array($r['status'],['accepted','enrolled'],true)) echo '<form class="d-inline ms-1" method="post" action="'.e(url(['action'=>'enroll'])).'">'.csrf_field().'<input type="hidden" name="id" value="'.e((string)$r['id']).'"><button class="btn btn-sm btn-success">Enroll</button></form>';
        } else echo '<span class="text-muted">View only</span>';
        echo '</td></tr>';
    }
    $extraColumns=($key==='books'?1:0)+($key==='exam_marks'?2:0)+($key==='payments'?2:0)+($key==='fee_charges'?1:0)+($key==='library_loans'?1:0);
    if(!$records) echo '<tr><td colspan="'.(2+count($m['fields'])+$extraColumns).'" class="text-center text-muted py-4">No records found. Add the first record to begin.</td></tr>';
    echo '</tbody></table></div>';
    if($key==='exams') echo '<p class="small text-muted mt-3">Results appear to students and parents only when the exam is published.</p>';
    if($key==='exam_marks') echo '<p class="small text-muted mt-3">Maximum marks come from the selected exam. Per-subject grades are calculated automatically; published exams are locked until unpublished.</p>';
    end_page();
}
function login_view(): void {
    if(current_user()) redirect();
    begin_page('Sign in');
    echo '<div class="auth-shell">
        <aside class="auth-brand-panel">
            <header class="auth-brand-header">
                <div class="brand-wrap">
                    <span class="brand-icon" aria-hidden="true">🎓</span>
                    <div class="brand-copy">
                        <h1>College ERP System</h1>
                        <small>Smart Management for a Better Future</small>
                    </div>
                </div>
            </header>
            <section class="auth-promo-card">
                <div class="auth-promo-copy">
                    <span class="auth-kicker">CAMPUS MANAGEMENT</span>
                    <h2>Manage Your<br>College Better</h2>
                    <div class="auth-promo-tags"><span>One Platform</span><span>All Modules</span></div>
                    <div class="auth-promo-audience"><span>Students</span><span>Faculty</span><span>Admin</span></div>
                </div>
                <div class="auth-illustration" aria-hidden="true"><div class="book-stack"></div><div class="cap"></div></div>
                <ul class="auth-feature-list">
                    <li>✓ Attendance Management</li>
                    <li>✓ Fees Management</li>
                    <li>✓ Examination &amp; Results</li>
                    <li>✓ Library, Hostel &amp; Transport</li>
                    <li>✓ Reports &amp; Analytics</li>
                </ul>
            </section>
            <footer class="auth-brand-footer"><span>© '.date('Y').' College ERP System</span><span>Education · Growth · Success</span></footer>
        </aside>
        <section class="auth-login-panel">
            <div class="auth-login-header"><span class="auth-secure-label">🔒 Secure role-based access</span></div>
            <div class="auth-form-card">
                <h2>Login</h2>
                <p class="auth-intro">Please enter your credentials to continue.</p>
                <form method="post" action="'.e(url(['action'=>'login'])).'" class="auth-form">'.csrf_field().'<input type="hidden" name="portal" value="">
                    <div class="role-grid" aria-label="Select your account portal">
                        <button type="button" class="role-pill" data-login-portal="admin" aria-pressed="false">👤<span>Admin</span></button>
                        <button type="button" class="role-pill" data-login-portal="faculty" aria-pressed="false">👥<span>Faculty</span></button>
                        <button type="button" class="role-pill" data-login-portal="student" aria-pressed="false">🎓<span>Student / Parent</span></button>
                    </div>
                    <div class="field-wrap">
                        <label class="visually-hidden" for="login-email">Email address</label>
                        <span class="field-icon" aria-hidden="true">👤</span>
                        <input id="login-email" type="email" name="email" placeholder="Enter your email address" required autocomplete="username">
                    </div>
                    <div class="field-wrap">
                        <label class="visually-hidden" for="login-password">Password</label>
                        <span class="field-icon" aria-hidden="true">🔒</span>
                        <input id="login-password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">◉</button>
                    </div>
                    <div class="auth-options">
                        <span>Use the credentials provided by your administrator.</span>
                    </div>
                    <button class="btn btn-primary auth-submit">→ Sign In</button>
                    <p class="auth-support">Need an account? Ask your college administrator to create one.</p>
                </form>
            </div>
            <footer class="auth-login-footer">College ERP · Secure campus access</footer>
        </section>
    </div>'; end_page();
}
function setup_view(): void {
    if(setup_complete()) { begin_page('Setup locked'); echo '<div class="alert alert-success">Super Admin setup is complete. The setup wizard is locked. <a href="'.e(url(['action'=>'login'])).'">Sign in</a>.</div>'; end_page(); return; }
    begin_page('Initial Super Admin setup');
    $us=[]; try{$us=db()->query('SELECT id,name FROM universities ORDER BY name')->fetchAll();}catch(Throwable $e){}
    echo '<div class="card auth-card mx-auto"><div class="card-body p-4"><h1 class="h3">Create the Super Admin</h1><p class="text-muted">This one-time setup is disabled after the first administrator is created.</p>';
    if(!$us) echo '<div class="alert alert-warning">Import <code>database/schema.sql</code> and configure the database before setup.</div>';
    else { echo '<form method="post" action="'.e(url(['action'=>'setup'])).'">'.csrf_field().'<div class="mb-3"><label class="form-label">University</label><select name="university_id" class="form-select" required>';
    foreach($us as $un) echo '<option value="'.e((string)$un['id']).'">'.e($un['name']).'</option>';
    echo '</select></div><div class="mb-3"><label class="form-label">Full name</label><input class="form-control" name="full_name" required maxlength="160"></div><div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required maxlength="190"></div><div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="12" required autocomplete="new-password"><div class="form-text">Use at least 12 characters with a mix of letters, numbers, and symbols.</div></div><div class="mb-3"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="confirm_password" minlength="12" required autocomplete="new-password"></div><button class="btn btn-primary w-100" '.(!$us?'disabled':'').'>Create Super Admin</button></form>'; }
    echo '</div></div>'; end_page();
}

<?php
require_once __DIR__ . '/bootstrap.php';

function setup_complete(): bool {
    try { return (int)db()->query("SELECT COUNT(*) FROM users WHERE role='super_admin'")->fetchColumn() > 0; }
    catch (Throwable $e) { return false; }
}
function module_scope(string $key, array $m): array {
    $u = require_login(); $table = $m['table'];
    if ($u['role'] === 'super_admin' || ($m['scope'] ?? '') === 'global') return ['', []];
    if ($u['role'] === 'university_admin') return [' WHERE t.university_id = ?', [(int)$u['university_id']]];
    if ($u['role'] === 'student') {
        if ($table === 'students') return [' WHERE t.id = ?', [(int)$u['student_id']]];
        if ($table === 'books') return [' WHERE t.college_id = ?', [(int)$u['college_id']]];
        if (array_key_exists('student_id', $m['fields'])) return [' WHERE t.student_id = ?', [(int)$u['student_id']]];
    }
    if ($u['role'] === 'teacher' && $table==='students') {
        return [' WHERE t.college_id=? AND t.id IN (SELECT st.id FROM students st JOIN classes cl ON cl.college_id=st.college_id AND cl.name=st.class_name JOIN teacher_assignments ta ON ta.class_id=cl.id JOIN staff sf ON sf.id=ta.teacher_id WHERE sf.user_id=? AND ta.college_id=?)',
            [(int)$u['college_id'],(int)$u['id'],(int)$u['college_id']]];
    }
    if ($u['role'] === 'parent') {
        if ($table === 'students') return [' WHERE t.id IN (SELECT student_id FROM parent_students WHERE parent_user_id = ?)', [(int)$u['id']]];
        if (array_key_exists('student_id', $m['fields'])) return [' WHERE t.student_id IN (SELECT student_id FROM parent_students WHERE parent_user_id = ?)', [(int)$u['id']]];
    }
    if ($u['role'] === 'teacher' && in_array($key,['attendance','exam_marks'],true)) {
        $base=' WHERE t.college_id = ? AND t.student_id IN (SELECT st.id FROM students st JOIN classes cl ON cl.college_id=st.college_id AND cl.name=st.class_name JOIN teacher_assignments ta ON ta.class_id=cl.id JOIN staff sf ON sf.id=ta.teacher_id WHERE sf.user_id=? AND ta.college_id=?)';
        $params=[(int)$u['college_id'],(int)$u['id'],(int)$u['college_id']];
        if($key==='exam_marks') {
            $base.=' AND t.subject IN (SELECT su.name FROM subjects su JOIN teacher_assignments ta ON ta.subject_id=su.id JOIN staff sf ON sf.id=ta.teacher_id WHERE sf.user_id=? AND ta.college_id=?)';
            $params[]=(int)$u['id'];$params[]=(int)$u['college_id'];
        }
        return [$base,$params];
    }
    return [' WHERE t.college_id = ?', [(int)$u['college_id']]];
}
function module_can_write(string $key): bool {
    $role = current_user()['role'];
    if (in_array($role, ['super_admin','university_admin','college_admin'], true)) return true;
    return $role === 'teacher' && in_array($key, ['attendance','exam_marks'], true);
}
function scoped_fk_exists(string $table, int $id): bool {
    $u = require_login();
    if ($u['role'] === 'super_admin') $q = db()->prepare("SELECT COUNT(*) FROM `$table` WHERE id=?");
    elseif ($u['role'] === 'university_admin') $q = db()->prepare("SELECT COUNT(*) FROM `$table` WHERE id=? AND university_id=?");
    else $q = db()->prepare("SELECT COUNT(*) FROM `$table` WHERE id=? AND college_id=?");
    $params = [$id];
    if ($u['role'] !== 'super_admin') $params[] = $u[$u['role']==='university_admin'?'university_id':'college_id'];
    $q->execute($params); return (int)$q->fetchColumn() === 1;
}
function field_values(array $m, bool $editing, ?array $record): array {
    $values=[]; $errors=[];
    foreach ($m['fields'] as $name=>$meta) {
        [$label,$type,$required]=$meta; $v=trim((string)($_POST[$name] ?? ''));
        if ($v === '' && $required) { $errors[]="$label is required."; continue; }
        if ($v === '') { $values[$name]=null; continue; }
        if ($type === 'email' && !filter_var($v,FILTER_VALIDATE_EMAIL)) { $errors[]="$label must be a valid email."; continue; }
        if ($type === 'integer' && (!ctype_digit($v) || (int)$v < 0)) { $errors[]="$label must be a non-negative whole number."; continue; }
        if ($type === 'decimal' && (!is_numeric($v) || (float)$v < 0 || (float)$v > 999999999)) { $errors[]="$label must be a non-negative amount."; continue; }
        if (in_array($name,['amount','max_marks','capacity','copies'],true) && (float)$v<=0) { $errors[]="$label must be greater than zero."; continue; }
        if ($type === 'date') { $d=DateTime::createFromFormat('Y-m-d',$v); if (!$d || $d->format('Y-m-d') !== $v) { $errors[]="$label must be a valid date."; continue; } }
        if ($type === 'yesno') { if (!in_array($v,['0','1'],true)) { $errors[]="$label has an invalid value."; continue; } $v=(int)$v; }
        if (str_starts_with($type,'select:') && !in_array($v,explode(',',substr($type,7)),true)) { $errors[]="$label has an invalid selection."; continue; }
        if (str_starts_with($type,'ref:')) {
            [, $ref] = explode(':',$type,2); [$refTable] = explode(':',$ref);
            if (!ctype_digit($v) || !scoped_fk_exists($refTable,(int)$v)) { $errors[]="$label selection is not available in your organization."; continue; }
            $v=(int)$v;
        }
        if (strlen($v)>4000) { $errors[]="$label is too long."; continue; }
        $values[$name]=$v;
    }
    return [$values,$errors];
}
function ref_options(string $type): array {
    if (!str_starts_with($type,'ref:')) return [];
    [, $spec]=explode(':',$type,2); [$table,$label]=explode(':',$spec,2);
    $u=require_login();
    if ($u['role']==='super_admin') $sql="SELECT id, `$label` label FROM `$table` ORDER BY `$label`";
    elseif ($u['role']==='university_admin') $sql="SELECT id, `$label` label FROM `$table` WHERE university_id=? ORDER BY `$label`";
    else $sql="SELECT id, `$label` label FROM `$table` WHERE college_id=? ORDER BY `$label`";
    $q=db()->prepare($sql); if($u['role']!=='super_admin') $q->execute([(int)$u[$u['role']==='university_admin'?'university_id':'college_id']]); else $q->execute();
    return $q->fetchAll();
}
function list_records(string $key, array $m): array {
    [$where,$params]=module_scope($key,$m);
    if ($key==='exam_marks' && in_array(current_user()['role'],['student','parent'],true)) {
        $where .= ($where ? ' AND ' : ' WHERE ') . 't.exam_id IN (SELECT id FROM exams WHERE published=1)';
    }
    $q=db()->prepare("SELECT t.* FROM `{$m['table']}` t{$where} ORDER BY t.id DESC LIMIT 300"); $q->execute($params);
    return $q->fetchAll();
}
function get_record(string $key,array $m,int $id): ?array {
    [$where,$params]=module_scope($key,$m); $where=$where ? $where.' AND t.id=?' : ' WHERE t.id=?'; $params[]=$id;
    $q=db()->prepare("SELECT t.* FROM `{$m['table']}` t{$where} LIMIT 1"); $q->execute($params); return $q->fetch() ?: null;
}
function handle_module_post(string $key,array $m): void {
    verify_csrf(); if (!module_can_write($key)) { http_response_code(403); exit('Read-only access.'); }
    $action=$_POST['do'] ?? '';
    $id=(int)($_POST['id'] ?? 0);
    if ($action==='delete') {
        if(!$id || !get_record($key,$m,$id)) { flash('danger','Record not found in your organization.'); redirect(['module'=>$key]); }
        if($key==='students') {
            $related=0;
            foreach(['attendance','fee_charges','payments','exam_marks','library_loans','hostel_allocations','transport_assignments'] as $rt) {
                $cq=db()->prepare("SELECT COUNT(*) FROM `$rt` WHERE student_id=?");$cq->execute([$id]);$related+=(int)$cq->fetchColumn();
            }
            if($related){flash('danger','Student has linked academic or financial history. Change status to withdrawn instead of deleting.');redirect(['module'=>$key]);}
        }
        if($key==='universities') {
            $cq=db()->prepare('SELECT COUNT(*) FROM colleges WHERE university_id=?');$cq->execute([$id]);
            if((int)$cq->fetchColumn()){flash('danger','University still contains colleges. Reassign or remove empty colleges first.');redirect(['module'=>$key]);}
        }
        if($key==='colleges') {
            $related=0;
            foreach(['users','admissions','students','staff','academic_years','classes','sections','subjects','teacher_assignments','attendance','fee_types','fee_charges','payments','exams','exam_marks','books','library_loans','hostels','hostel_rooms','hostel_allocations','transport_routes','transport_vehicles','transport_assignments'] as $rt) {
                $cq=db()->prepare("SELECT COUNT(*) FROM `$rt` WHERE college_id=?");$cq->execute([$id]);$related+=(int)$cq->fetchColumn();
            }
            if($related){flash('danger','College contains operational records. Deactivate it instead of deleting its history.');redirect(['module'=>$key]);}
        }
        if($key==='exams') {
            $cq=db()->prepare('SELECT COUNT(*) FROM exam_marks WHERE exam_id=?');$cq->execute([$id]);
            if((int)$cq->fetchColumn()){flash('danger','Exam has marks. Remove the marks first, or keep the exam record for its history.');redirect(['module'=>$key]);}
        }
        if($key==='staff') {
            $cq=db()->prepare('SELECT COUNT(*) FROM teacher_assignments WHERE teacher_id=?');$cq->execute([$id]);
            if((int)$cq->fetchColumn()){flash('danger','Staff member has active teaching assignments. Remove those assignments first.');redirect(['module'=>$key]);}
        }
        if ($key==='payments') db()->beginTransaction();
        try { $q=db()->prepare("DELETE FROM `{$m['table']}` WHERE id=?"); $q->execute([$id]); if(db()->inTransaction()) db()->commit(); flash('success','Record deleted.'); }
        catch(Throwable $e){ if(db()->inTransaction()) db()->rollBack(); flash('danger','Delete failed. Related records may depend on this item.'); }
        redirect(['module'=>$key]);
    }
    $existing=$id ? get_record($key,$m,$id) : null;
    if($id && !$existing){ flash('danger','Record not found in your organization.'); redirect(['module'=>$key]); }
    [$values,$errors]=field_values($m,(bool)$id,$existing);
    $u=require_login();
    if($u['role']==='teacher' && !$errors) {
        $assigned=teacher_assigned_student((int)$values['student_id'],$key==='exam_marks'?(string)$values['subject']:null);
        if(!$assigned) $errors[]='You can only update students in your assigned class and subjects.';
    }
    if($errors){ $_SESSION['errors']=$errors; $_SESSION['form_old']=$_POST; redirect(['module'=>$key,'edit'=>$id ?: '']); }
    // Never trust tenant identifiers submitted by the browser.
    $columns=db()->query("SHOW COLUMNS FROM `{$m['table']}`")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['university_id','college_id'] as $tenant) {
        if (!in_array($tenant,$columns,true)) continue;
        if(in_array($u['role'],['super_admin','university_admin'],true) && $tenant==='college_id' && isset($_POST[$tenant]) && ctype_digit((string)$_POST[$tenant])) {
            $tid=(int)$_POST[$tenant];
            if($tenant==='college_id') {
                $check=db()->prepare('SELECT id FROM colleges WHERE id=?'.($u['role']==='university_admin'?' AND university_id=?':''));
                $params=[$tid];if($u['role']==='university_admin')$params[]=$u['university_id'];$check->execute($params);
                if(!$check->fetchColumn()) { flash('danger','Invalid college selection.');redirect(['module'=>$key]); }
            }
            $values[$tenant]=$tid;
        } elseif($u['role']==='super_admin' && $tenant==='university_id' && $key==='colleges' && isset($_POST[$tenant]) && ctype_digit((string)$_POST[$tenant])) {
            $tid=(int)$_POST[$tenant];if(!scoped_fk_exists('universities',$tid)){flash('danger','Invalid university selection.');redirect(['module'=>$key]);}$values[$tenant]=$tid;
        } elseif($u['role']!=='super_admin') $values[$tenant]=$tenant==='university_id' ? (int)$u['university_id'] : (int)$u['college_id'];
    }
    function teacher_assigned_student(int $studentId, ?string $subject): bool {
        $sql="SELECT COUNT(*) FROM students st JOIN classes cl ON cl.college_id=st.college_id AND cl.name=st.class_name JOIN teacher_assignments ta ON ta.class_id=cl.id JOIN staff sf ON sf.id=ta.teacher_id WHERE st.id=? AND sf.user_id=? AND ta.college_id=?";
        $params=[$studentId,(int)current_user()['id'],(int)current_user()['college_id']];
        if($subject!==null) {$sql.=" AND EXISTS(SELECT 1 FROM subjects su WHERE su.id=ta.subject_id AND su.name=?)";$params[]=$subject;}
        $q=db()->prepare($sql);$q->execute($params);return (int)$q->fetchColumn()>0;
    }
    if($u['role']==='super_admin' && in_array('college_id',$columns,true)) {
        $lookup=db()->prepare('SELECT university_id FROM colleges WHERE id=?'); $lookup->execute([(int)($values['college_id']??0)]);
        $uni=$lookup->fetchColumn(); if($uni) $values['university_id']=(int)$uni;
    }
    if (in_array('recorded_by',$columns,true)) $values['recorded_by']=(int)$u['id'];
    if (in_array('received_by',$columns,true)) $values['received_by']=(int)$u['id'];
    if (in_array('entered_by',$columns,true)) $values['entered_by']=(int)$u['id'];
    if(isset($values['college_id'])) {
        foreach($m['fields'] as $fname=>$fmeta) if(str_starts_with($fmeta[1],'ref:') && !empty($values[$fname])) {
            [, $spec]=explode(':',$fmeta[1],2);[$refTable]=explode(':',$spec);
            $check=db()->prepare("SELECT COUNT(*) FROM `$refTable` WHERE id=? AND college_id=?");$check->execute([(int)$values[$fname],(int)$values['college_id']]);
            if(!(int)$check->fetchColumn()) {flash('danger','Related records must belong to the selected college.');redirect(['module'=>$key]);}
        }
    }
    try {
        if(!db()->inTransaction() && in_array($key,['payments','fee_charges','library_loans','hostel_allocations','transport_assignments','books','hostel_rooms','transport_vehicles'],true)) db()->beginTransaction();
        if($key==='fee_charges') {
            $locked=db()->prepare('SELECT student_id FROM fee_charges WHERE id=? FOR UPDATE');$locked->execute([$id]);$oldCharge=$id?$locked->fetch():false;
            $paid=db()->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE charge_id=?');$paid->execute([$id]);$paidAmount=(float)$paid->fetchColumn();
            if($oldCharge && $paidAmount>0 && (int)$values['student_id']!==(int)$oldCharge['student_id']) throw new RuntimeException('A charge with payments cannot be reassigned to another student.');
            if((float)$values['amount']+0.001<$paidAmount) throw new RuntimeException('Charge amount cannot be less than payments already recorded.');
        }
        if($key==='payments') {
            $charge=db()->prepare('SELECT student_id,amount FROM fee_charges WHERE id=? FOR UPDATE'); $charge->execute([(int)$values['charge_id']]); $c=$charge->fetch();
            if(!$c || !scoped_fk_exists('fee_charges',(int)$values['charge_id'])) throw new RuntimeException('Invalid charge.');
            $paid=db()->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE charge_id=? AND id<>?'); $paid->execute([(int)$values['charge_id'],$id]);
            if((float)$values['amount'] > (float)$c['amount']-(float)$paid->fetchColumn()+0.001) throw new RuntimeException('Payment exceeds outstanding balance.');
            $values['student_id']=(int)$c['student_id'];
            if((float)$values['amount']<=0) throw new RuntimeException('Payment amount must be greater than zero.');
            if(!$id) $values['receipt_no']='R'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
        }
        if($key==='exam_marks') {
            if($id && !empty($existing['exam_id'])) {
                $prior=db()->prepare('SELECT published FROM exams WHERE id=?');$prior->execute([(int)$existing['exam_id']]);
                if((int)$prior->fetchColumn()===1) throw new RuntimeException('Published exam results are locked. Unpublish the exam before editing marks.');
            }
            $ex=db()->prepare('SELECT published,max_marks FROM exams WHERE id=?');$ex->execute([(int)$values['exam_id']]);$exam=$ex->fetch();
            if(!$exam) throw new RuntimeException('Invalid exam.');
            if((int)$exam['published']===1) throw new RuntimeException('Published exam results are locked. Unpublish the exam before editing marks.');
            $values['max_marks']=(float)$exam['max_marks'];
            if((float)$values['marks']>(float)$values['max_marks']) throw new RuntimeException('Marks cannot exceed the exam maximum.');
            $pct=(float)$values['max_marks']>0?100*(float)$values['marks']/(float)$values['max_marks']:0;
            $values['grade']=$pct>=90?'A':($pct>=80?'B':($pct>=70?'C':($pct>=60?'D':'F')));
        }
        if($key==='books' && $id) {
            $lock=db()->prepare('SELECT id FROM books WHERE id=? FOR UPDATE');$lock->execute([$id]);
            $q=db()->prepare("SELECT COUNT(*) FROM library_loans WHERE book_id=? AND status='issued'");$q->execute([$id]);
            if((int)$values['copies']<(int)$q->fetchColumn()) throw new RuntimeException('Book copies cannot be lower than the number currently issued.');
        }
        if($key==='hostel_rooms' && $id) {
            $lock=db()->prepare('SELECT id FROM hostel_rooms WHERE id=? FOR UPDATE');$lock->execute([$id]);
            $q=db()->prepare("SELECT COUNT(*) FROM hostel_allocations WHERE room_id=? AND status='active'");$q->execute([$id]);
            if((int)$values['capacity']<(int)$q->fetchColumn()) throw new RuntimeException('Room capacity cannot be lower than current occupancy.');
        }
        if($key==='transport_vehicles' && $id) {
            $lock=db()->prepare('SELECT id FROM transport_vehicles WHERE id=? FOR UPDATE');$lock->execute([$id]);
            $q=db()->prepare("SELECT COUNT(*) FROM transport_assignments WHERE vehicle_id=? AND status='active'");$q->execute([$id]);
            if((int)$values['capacity']<(int)$q->fetchColumn()) throw new RuntimeException('Vehicle capacity cannot be lower than current assignments.');
        }
        if($key==='library_loans' && $values['status']==='issued') {
            $book=db()->prepare('SELECT copies FROM books WHERE id=? FOR UPDATE');$book->execute([(int)$values['book_id']]);$copies=$book->fetchColumn();
            if($copies===false || !scoped_fk_exists('books',(int)$values['book_id'])) throw new RuntimeException('Invalid book.');
            $active=db()->prepare("SELECT COUNT(*) FROM library_loans WHERE book_id=? AND status='issued' AND id<>?");$active->execute([(int)$values['book_id'],$id]);
            if((int)$active->fetchColumn()>=(int)$copies) throw new RuntimeException('All catalogued copies are currently issued.');
        }
        if($key==='hostel_allocations' && $values['status']==='active') {
            $room=db()->prepare('SELECT capacity FROM hostel_rooms WHERE id=? FOR UPDATE');$room->execute([(int)$values['room_id']]);$capacity=$room->fetchColumn();
            if($capacity===false || !scoped_fk_exists('hostel_rooms',(int)$values['room_id'])) throw new RuntimeException('Invalid room.');
            $occupied=db()->prepare("SELECT COUNT(*) FROM hostel_allocations WHERE room_id=? AND status='active' AND id<>?");$occupied->execute([(int)$values['room_id'],$id]);
            if((int)$occupied->fetchColumn()>=(int)$capacity) throw new RuntimeException('This room has reached its capacity.');
            $other=db()->prepare("SELECT COUNT(*) FROM hostel_allocations WHERE student_id=? AND status='active' AND id<>?");$other->execute([(int)$values['student_id'],$id]);
            if((int)$other->fetchColumn()>0) throw new RuntimeException('This student already has an active hostel allocation.');
        }
        if($key==='transport_assignments' && $values['vehicle_id'] && $values['status']==='active') {
            $vehicle=db()->prepare('SELECT capacity FROM transport_vehicles WHERE id=? FOR UPDATE');$vehicle->execute([(int)$values['vehicle_id']]);$capacity=$vehicle->fetchColumn();
            if($capacity===false || !scoped_fk_exists('transport_vehicles',(int)$values['vehicle_id'])) throw new RuntimeException('Invalid vehicle.');
            $assigned=db()->prepare("SELECT COUNT(*) FROM transport_assignments WHERE vehicle_id=? AND status='active' AND id<>?");$assigned->execute([(int)$values['vehicle_id'],$id]);
            if((int)$assigned->fetchColumn()>=(int)$capacity) throw new RuntimeException('This vehicle has reached its passenger capacity.');
        }
        if($key==='transport_assignments' && $values['status']==='active') {
            $other=db()->prepare("SELECT COUNT(*) FROM transport_assignments WHERE student_id=? AND status='active' AND id<>?");$other->execute([(int)$values['student_id'],$id]);
            if((int)$other->fetchColumn()>0) throw new RuntimeException('This student already has an active transport assignment.');
        }
        if($id) {
            unset($values['college_id'],$values['university_id']);
            $sets=array_map(fn($c)=>"`$c`=?",array_keys($values)); $params=array_values($values); $params[]=$id;
            $scope=scope_sql(''); $sql="UPDATE `{$m['table']}` SET ".implode(',',$sets)." WHERE id=?";
            // update is constrained by retrieving this record via tenant-scoped lookup first.
            db()->prepare($sql)->execute($params);
        } else {
            $cols=array_keys($values); $sql="INSERT INTO `{$m['table']}` (`".implode('`,`',$cols)."`) VALUES (".implode(',',array_fill(0,count($cols),'?')).")";
            db()->prepare($sql)->execute(array_values($values));
        }
        if(db()->inTransaction()) db()->commit();
        flash('success',$id?'Record updated.':'Record created.');
    } catch(Throwable $e) {
        if(db()->inTransaction()) db()->rollBack();
        flash('danger',$e instanceof RuntimeException ? $e->getMessage() : 'Save failed. Check required relationships, duplicate codes, and field values.');
    }
    redirect(['module'=>$key]);
}
function create_enrollment(int $admissionId): void {
    verify_csrf(); require_role(['super_admin','university_admin','college_admin']);
    $q=db()->prepare('SELECT * FROM admissions WHERE id=?'); $q->execute([$admissionId]); $a=$q->fetch();
    if(!$a){flash('danger','Application not found.');redirect(['module'=>'admissions']);}
    // Recheck tenant access using the scoped resource query.
    $m=modules()['admissions']; if(!get_record('admissions',$m,$admissionId)){flash('danger','Application not found in your organization.');redirect(['module'=>'admissions']);}
    if(!in_array($a['status'],['accepted','enrolled'],true)){flash('danger','Only accepted applications can be enrolled.');redirect(['module'=>'admissions']);}
    db()->beginTransaction();
    try {
        $find=db()->prepare('SELECT id FROM students WHERE college_id=? AND admission_no=?'); $find->execute([$a['college_id'],$a['application_no']]);
        if(!$find->fetchColumn()) {
            $ins=db()->prepare("INSERT INTO students(university_id,college_id,admission_no,full_name,email,phone,class_name,status) VALUES(?,?,?,?,?,?,?,'active')");
            $ins->execute([$a['university_id'],$a['college_id'],$a['application_no'],$a['applicant_name'],$a['email'],$a['phone'],$a['program']]);
        }
        db()->prepare("UPDATE admissions SET status='enrolled' WHERE id=?")->execute([$admissionId]);
        db()->commit(); flash('success','Application enrolled. Student record created.');
    } catch(Throwable $e){db()->rollBack();flash('danger','Enrollment failed; no changes were saved.');}
    redirect(['module'=>'admissions']);
}

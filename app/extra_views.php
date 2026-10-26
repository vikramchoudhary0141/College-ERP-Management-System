<?php
function accounts_view(): void {
    require_login(); if(!is_admin()){http_response_code(403);exit('Access denied.');}
    $u=current_user(); begin_page('User accounts');
    $cs=[]; if($u['role']==='super_admin') $cs=db()->query('SELECT id,name,university_id FROM colleges ORDER BY name')->fetchAll();
    elseif($u['role']==='university_admin') {$q=db()->prepare('SELECT id,name,university_id FROM colleges WHERE university_id=? ORDER BY name');$q->execute([$u['university_id']]);$cs=$q->fetchAll();}
    else {$q=db()->prepare('SELECT id,name,university_id FROM colleges WHERE id=?');$q->execute([$u['college_id']]);$cs=$q->fetchAll();}
    echo '<h1 class="h3 mb-3">User accounts</h1><div class="row g-4"><div class="col-xl-5"><section class="card"><div class="card-header fw-semibold">Create account</div><div class="card-body"><form method="post" action="'.e(url(['action'=>'accounts'])).'">'.csrf_field().'<input type="hidden" name="do" value="create">';
    echo '<div class="mb-3"><label class="form-label">Full name</label><input class="form-control" name="full_name" maxlength="160" required></div><div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" maxlength="190" required></div><div class="mb-3"><label class="form-label">Temporary password</label><input class="form-control" type="password" name="password" minlength="12" required></div><div class="mb-3"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="confirm_password" minlength="12" required></div>';
    echo '<div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role" required>';
    $roles=$u['role']==='super_admin'?['university_admin','college_admin','teacher','student','parent']:($u['role']==='university_admin'?['college_admin','teacher','student','parent']:['teacher','student','parent']);
    foreach($roles as $r) echo '<option value="'.e($r).'">'.e(role_name($r)).'</option>';
    echo '</select></div><div class="mb-3"><label class="form-label">College (required except University Admin)</label><select class="form-select" name="college_id"><option value="">Select college</option>';
    foreach($cs as $c) echo '<option value="'.e((string)$c['id']).'">'.e($c['name']).'</option>';
    echo '</select></div><div class="mb-3"><label class="form-label">Student record (for Student account)</label><select class="form-select" name="student_id"><option value="">Not linked</option>';
    if($u['role']==='super_admin') $stud=db()->query("SELECT id,full_name,admission_no FROM students ORDER BY full_name")->fetchAll();
    elseif($u['role']==='university_admin') {$q=db()->prepare("SELECT id,full_name,admission_no FROM students WHERE university_id=? ORDER BY full_name");$q->execute([$u['university_id']]);$stud=$q->fetchAll();}
    else {$q=db()->prepare("SELECT id,full_name,admission_no FROM students WHERE college_id=? ORDER BY full_name");$q->execute([$u['college_id']]);$stud=$q->fetchAll();}
    foreach($stud as $s) echo '<option value="'.e((string)$s['id']).'">'.e($s['full_name'].' · '.$s['admission_no']).'</option>';
    echo '</select></div><div class="mb-3"><label class="form-label">Teacher staff record (for Teacher account)</label><select class="form-select" name="staff_id"><option value="">Not linked</option>';
    if($u['role']==='super_admin')$teachers=db()->query('SELECT id,full_name,employee_no FROM staff ORDER BY full_name')->fetchAll();
    elseif($u['role']==='university_admin'){$sq=db()->prepare('SELECT id,full_name,employee_no FROM staff WHERE university_id=? ORDER BY full_name');$sq->execute([$u['university_id']]);$teachers=$sq->fetchAll();}
    else{$sq=db()->prepare('SELECT id,full_name,employee_no FROM staff WHERE college_id=? ORDER BY full_name');$sq->execute([$u['college_id']]);$teachers=$sq->fetchAll();}
    foreach($teachers as $t)echo '<option value="'.e((string)$t['id']).'">'.e($t['full_name'].' · '.$t['employee_no']).'</option>';
    echo '</select></div>';
    if($u['role']==='super_admin') {
        $universities=db()->query('SELECT id,name FROM universities ORDER BY name')->fetchAll();
        echo '<div class="mb-3"><label class="form-label">University (for University Admin)</label><select class="form-select" name="account_university_id"><option value="">Select university</option>';
        foreach($universities as $uni) echo '<option value="'.e((string)$uni['id']).'">'.e($uni['name']).'</option>';
        echo '</select></div>';
    }
    echo '<div class="mb-3"><label class="form-label">Linked students (for Parent account)</label><select class="form-select" name="parent_student_ids[]" multiple size="5">';
    foreach($stud as $s) echo '<option value="'.e((string)$s['id']).'">'.e($s['full_name'].' · '.$s['admission_no']).'</option>';
    echo '</select><div class="form-text">Select only the student records associated with this parent.</div></div><button class="btn btn-primary">Create account</button></form></div></section></div><div class="col-xl-7"><section class="card"><div class="card-header fw-semibold">Accounts</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>';
    if($u['role']==='super_admin') $users=db()->query('SELECT id,full_name,email,role,active FROM users ORDER BY id DESC LIMIT 300')->fetchAll();
    elseif($u['role']==='university_admin') {$q=db()->prepare('SELECT id,full_name,email,role,active FROM users WHERE university_id=? ORDER BY id DESC LIMIT 300');$q->execute([$u['university_id']]);$users=$q->fetchAll();}
    else {$q=db()->prepare('SELECT id,full_name,email,role,active FROM users WHERE college_id=? ORDER BY id DESC LIMIT 300');$q->execute([$u['college_id']]);$users=$q->fetchAll();}
    foreach($users as $person) {
        echo '<tr><td>'.e($person['full_name']).'</td><td>'.e($person['email']).'</td><td>'.e(role_name($person['role'])).'</td><td>'.($person['active']?'Active':'Disabled').'</td><td>';
        if((int)$person['id']!==(int)$u['id']) {
            echo '<form class="d-inline-block me-1 mb-1" method="post" action="'.e(url(['action'=>'accounts'])).'">'.csrf_field().'<input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="'.e((string)$person['id']).'"><button class="btn btn-sm btn-outline-'.($person['active']?'danger':'success').'">'.($person['active']?'Deactivate':'Activate').'</button></form>';
            if($person['role']!=='super_admin') {
                echo '<details class="account-reset-form"><summary class="btn btn-sm btn-outline-primary">Reset password</summary><form method="post" action="'.e(url(['action'=>'accounts'])).'" class="card card-body mt-2">'.csrf_field().'<input type="hidden" name="do" value="reset_password"><input type="hidden" name="id" value="'.e((string)$person['id']).'"><label class="form-label" for="reset-password-'.e((string)$person['id']).'">New password</label><input class="form-control mb-2" id="reset-password-'.e((string)$person['id']).'" type="password" name="new_password" minlength="12" autocomplete="new-password" required><label class="form-label" for="confirm-reset-password-'.e((string)$person['id']).'">Confirm new password</label><input class="form-control mb-2" id="confirm-reset-password-'.e((string)$person['id']).'" type="password" name="confirm_password" minlength="12" autocomplete="new-password" required><small class="form-text mb-2">12+ characters with letters, a number, and a symbol. Give it to the user privately.</small><button class="btn btn-primary btn-sm">Set new password</button></form></details>';
            }
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div></section></div></div>';
    end_page();
}
function reports_view(): void {
    require_login(); if(!is_admin()){http_response_code(403);exit('Access denied.');}
    $from=(string)($_GET['from']??''); $to=(string)($_GET['to']??'');
    foreach(['from','to'] as $field) { $d=$field==='from'?$from:$to; if($d!=='' && (!DateTime::createFromFormat('Y-m-d',$d) || DateTime::createFromFormat('Y-m-d',$d)->format('Y-m-d')!==$d)) { if($field==='from')$from='';else $to=''; } }
    begin_page('Reports'); echo '<h1 class="h3 mb-3">Operational reports</h1><p class="text-muted">Live summaries from your accessible colleges. Use date filters and Print to save or print reports.</p>';
    echo '<form class="card card-body mb-3 no-print" method="get" action="'.e(url()).'"><input type="hidden" name="action" value="reports"><div class="row g-2 align-items-end"><div class="col-sm-4"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="'.e($from).'"></div><div class="col-sm-4"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="'.e($to).'"></div><div class="col-sm-4"><button class="btn btn-primary">Apply filters</button> <a class="btn btn-outline-secondary" href="'.e(url(['action'=>'reports'])).'">Clear</a></div></div></form>';
    $sets=[
        ['Admissions','admissions','SELECT status,COUNT(*) total FROM admissions t','status'],
        ['Student roster','students','SELECT status,COUNT(*) total FROM students t','status'],
        ['Staff roster','staff','SELECT job_title,COUNT(*) total FROM staff t','job_title'],
        ['Attendance','attendance','SELECT status,COUNT(*) total FROM attendance t','status'],
        ['Fee payments','payments','SELECT method,COUNT(*) transactions,SUM(amount) amount FROM payments t','method'],
        ['Exam results','exam_marks','SELECT subject,COUNT(*) total,AVG(marks) average_marks FROM exam_marks t','subject'],
        ['Library circulation','library_loans','SELECT status,COUNT(*) total FROM library_loans t','status'],
        ['Hostel occupancy','hostel_allocations','SELECT status,COUNT(*) total FROM hostel_allocations t','status'],
        ['Transport assignments','transport_assignments','SELECT status,COUNT(*) total FROM transport_assignments t','status'],
    ];
    echo '<div class="row g-3">';
    foreach($sets as [$title,$table,$base,$label]) {
        [$where,$params]=module_scope_for_table($table);
        $dateColumn=['admissions'=>'application_date','students'=>'created_at','staff'=>'hire_date','attendance'=>'attendance_date','payments'=>'paid_on','library_loans'=>'issued_on','hostel_allocations'=>'starts_on','transport_assignments'=>'starts_on'][$table]??null;
        if($table==='exam_marks') {
            if($from!=='') {$where.=($where?' AND ':'WHERE ')."EXISTS(SELECT 1 FROM exams ex WHERE ex.id=t.exam_id AND ex.exam_date>=?)";$params[]=$from;}
            if($to!=='') {$where.=($where?' AND ':'WHERE ')."EXISTS(SELECT 1 FROM exams ex WHERE ex.id=t.exam_id AND ex.exam_date<=?)";$params[]=$to;}
        }
        if($dateColumn && $from!=='') {$where.=($where?' AND ':'WHERE ')."DATE(t.`$dateColumn`) >= ?";$params[]=$from;}
        if($dateColumn && $to!=='') {$where.=($where?' AND ':'WHERE ')."DATE(t.`$dateColumn`) <= ?";$params[]=$to;}
        $group=$base.' '.$where.' GROUP BY '.$label.' ORDER BY '.$label;
        $q=db()->prepare($group);$q->execute($params);$rows=$q->fetchAll();
        echo '<div class="col-lg-6"><section class="card report"><div class="card-header d-flex justify-content-between"><strong>'.e($title).'</strong><button onclick="window.print()" class="btn btn-sm btn-outline-primary no-print">Print</button></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>'.e(ucfirst(str_replace('_',' ',$label))).'</th><th>Count</th>';
        if($table==='payments') echo '<th>Amount</th>'; if($table==='exam_marks') echo '<th>Average marks</th>'; echo '</tr></thead><tbody>';
        foreach($rows as $row) { echo '<tr><td>'.e((string)$row[$label]).'</td><td>'.e((string)($row['total']??$row['transactions']??0)).'</td>';if(isset($row['amount']))echo '<td>'.number_format((float)$row['amount'],2).'</td>';if(isset($row['average_marks']))echo '<td>'.number_format((float)$row['average_marks'],2).'</td>';echo '</tr>'; }
        if(!$rows) echo '<tr><td colspan="3" class="text-muted">No data yet</td></tr>';
        echo '</tbody></table></div></section></div>';
    }
    echo '</div>'; end_page();
}
function module_scope_for_table(string $table): array {
    $u=require_login();
    if($u['role']==='super_admin') return ['',[]];
    if($u['role']==='university_admin') return ['WHERE t.university_id=?',[(int)$u['university_id']]];
    return ['WHERE t.college_id=?',[(int)$u['college_id']]];
}
function receipt_view(int $id): void {
    require_login(); require_role(['super_admin','university_admin','college_admin','student','parent']);
    $q=db()->prepare('SELECT p.*,s.full_name,s.admission_no,c.description,c.amount charge_amount,co.name college_name FROM payments p JOIN students s ON s.id=p.student_id JOIN fee_charges c ON c.id=p.charge_id JOIN colleges co ON co.id=p.college_id WHERE p.id=?');
    $q->execute([$id]); $r=$q->fetch();
    if(!$r) {http_response_code(404);exit('Receipt not found.');}
    if(current_user()['role']==='parent') {$check=db()->prepare('SELECT 1 FROM parent_students WHERE parent_user_id=? AND student_id=?');$check->execute([current_user()['id'],$r['student_id']]);$ok=$check->fetchColumn();}
    elseif(current_user()['role']==='student') $ok=(int)current_user()['student_id']===(int)$r['student_id'];
    elseif(current_user()['role']==='super_admin') $ok=true;
    elseif(current_user()['role']==='university_admin') $ok=(int)current_user()['university_id']===(int)$r['university_id'];
    else $ok=(int)current_user()['college_id']===(int)$r['college_id'];
    if(!$ok){http_response_code(403);exit('Access denied.');}
    begin_page('Payment receipt');
    echo '<div class="receipt card mx-auto"><div class="card-body p-4"><div class="d-flex justify-content-between"><div><h1 class="h3">'.e($r['college_name']).'</h1><div>Fee payment receipt</div></div><div class="text-end"><b>'.e($r['receipt_no']).'</b><br>'.e($r['paid_on']).'</div></div><hr><p><b>Student:</b> '.e($r['full_name']).' <span class="text-muted">('.e($r['admission_no']).')</span></p><p><b>Charge:</b> '.e($r['description']).'</p><p><b>Payment method:</b> '.e(ucwords(str_replace('_',' ',$r['method']))).'</p><p><b>Reference:</b> '.e($r['reference']?:'—').'</p><hr><div class="d-flex justify-content-between fs-5"><b>Amount received</b><b>'.number_format((float)$r['amount'],2).'</b></div><p class="text-muted mt-4 mb-0">Recorded in Campus ERP · Keep this receipt for your records.</p><button class="btn btn-primary mt-3 no-print" onclick="window.print()">Print receipt</button></div></div>';
    end_page();
}

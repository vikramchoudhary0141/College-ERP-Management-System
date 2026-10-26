<?php
function modules(): array
{
    $admin = ['super_admin','university_admin','college_admin'];
    $staff = array_merge($admin, ['teacher']);
    $student = ['student','parent'];
    $all = array_merge($admin, ['teacher','student']);
    return [
        'universities' => ['title'=>'Universities','table'=>'universities','scope'=>'global','roles'=>['super_admin'],'fields'=>[
            'name'=>['University name','text',true], 'code'=>['Code','text',true], 'address'=>['Address','text',false]]],
        'colleges' => ['title'=>'Colleges','table'=>'colleges','roles'=>['super_admin','university_admin'],'fields'=>[
            'name'=>['College name','text',true], 'code'=>['Code','text',true], 'address'=>['Address','text',false], 'phone'=>['Phone','text',false], 'active'=>['Active','yesno',true]]],
        'admissions' => ['title'=>'Admissions','table'=>'admissions','roles'=>$admin,'fields'=>[
            'application_no'=>['Application no.','text',true], 'applicant_name'=>['Applicant name','text',true], 'email'=>['Email','email',false],
            'phone'=>['Phone','text',false], 'program'=>['Program','text',true], 'application_date'=>['Application date','date',true],
            'status'=>['Status','select:submitted,under_review,accepted,rejected',true], 'notes'=>['Notes','textarea',false]]],
        'students' => ['title'=>'Students','table'=>'students','roles'=>array_merge($admin,['teacher','student','parent']),'fields'=>[
            'admission_no'=>['Admission no.','text',true], 'full_name'=>['Full name','text',true], 'email'=>['Email','email',false],
            'phone'=>['Phone','text',false], 'date_of_birth'=>['Date of birth','date',false], 'gender'=>['Gender','select:Female,Male,Other',false],
            'class_name'=>['Class','text',false], 'section_name'=>['Section','text',false], 'academic_year'=>['Academic year','text',false],
            'address'=>['Address','text',false], 'status'=>['Status','select:active,graduated,withdrawn',true]]],
        'staff' => ['title'=>'Staff & Teachers','table'=>'staff','roles'=>$admin,'fields'=>[
            'employee_no'=>['Employee no.','text',true], 'full_name'=>['Full name','text',true], 'email'=>['Email','email',false],
            'phone'=>['Phone','text',false], 'job_title'=>['Job title','text',true], 'hire_date'=>['Hire date','date',false],
            'status'=>['Status','select:active,inactive',true]]],
        'academic_years' => ['title'=>'Academic years','table'=>'academic_years','roles'=>$admin,'fields'=>[
            'name'=>['Year label','text',true], 'starts_on'=>['Starts','date',true], 'ends_on'=>['Ends','date',true], 'active'=>['Active','yesno',true]]],
        'classes' => ['title'=>'Classes','table'=>'classes','roles'=>$admin,'fields'=>[
            'name'=>['Class name','text',true], 'grade_level'=>['Grade level','text',false]]],
        'sections' => ['title'=>'Sections','table'=>'sections','roles'=>$admin,'fields'=>[
            'class_id'=>['Class','ref:classes:name',true], 'name'=>['Section','text',true]]],
        'subjects' => ['title'=>'Subjects','table'=>'subjects','roles'=>$admin,'fields'=>[
            'name'=>['Subject','text',true], 'code'=>['Code','text',true]]],
        'teacher_assignments' => ['title'=>'Teacher assignments','table'=>'teacher_assignments','roles'=>$admin,'fields'=>[
            'teacher_id'=>['Teacher staff record','ref:staff:full_name',true], 'class_id'=>['Class','ref:classes:name',true], 'subject_id'=>['Subject','ref:subjects:name',true]]],
        'attendance' => ['title'=>'Attendance','table'=>'attendance','roles'=>array_merge($staff,$student),'fields'=>[
            'student_id'=>['Student','ref:students:full_name',true], 'attendance_date'=>['Date','date',true],
            'status'=>['Status','select:present,absent,late,excused',true], 'remarks'=>['Remarks','text',false]]],
        'fee_types' => ['title'=>'Fee types','table'=>'fee_types','roles'=>$admin,'fields'=>[
            'name'=>['Fee name','text',true], 'default_amount'=>['Default amount','decimal',true], 'due_date'=>['Due date','date',false]]],
        'fee_charges' => ['title'=>'Student charges','table'=>'fee_charges','roles'=>array_merge($admin,$student),'fields'=>[
            'student_id'=>['Student','ref:students:full_name',true], 'fee_type_id'=>['Fee type','ref:fee_types:name',false],
            'description'=>['Description','text',true], 'amount'=>['Amount','decimal',true], 'due_date'=>['Due date','date',false]]],
        'payments' => ['title'=>'Fee payments & receipts','table'=>'payments','roles'=>array_merge($admin,$student),'fields'=>[
            'charge_id'=>['Student charge','ref:fee_charges:description',true], 'amount'=>['Payment amount','decimal',true],
            'paid_on'=>['Payment date','date',true], 'method'=>['Method','select:cash,card,bank_transfer,online,other',true], 'reference'=>['Reference','text',false]]],
        'exams' => ['title'=>'Examinations','table'=>'exams','roles'=>$admin,'fields'=>[
            'name'=>['Exam name','text',true], 'class_name'=>['Class','text',true], 'exam_date'=>['Exam date','date',true],
            'max_marks'=>['Maximum marks','decimal',true], 'published'=>['Published','yesno',true]]],
        'exam_marks' => ['title'=>'Exam marks & results','table'=>'exam_marks','roles'=>array_merge($staff,$student),'fields'=>[
            'exam_id'=>['Exam','ref:exams:name',true], 'student_id'=>['Student','ref:students:full_name',true],
            'subject'=>['Subject','text',true], 'marks'=>['Marks scored','decimal',true]]],
        'books' => ['title'=>'Library catalogue','table'=>'books','roles'=>$all,'fields'=>[
            'isbn'=>['ISBN','text',false], 'title'=>['Title','text',true], 'author'=>['Author','text',false],
            'category'=>['Category','text',false], 'copies'=>['Copies','integer',true]]],
        'library_loans' => ['title'=>'Library issue / return','table'=>'library_loans','roles'=>array_merge($admin,$student),'fields'=>[
            'book_id'=>['Book','ref:books:title',true], 'student_id'=>['Student','ref:students:full_name',true],
            'issued_on'=>['Issued on','date',true], 'due_on'=>['Due on','date',true],
            'returned_on'=>['Returned on','date',false], 'status'=>['Status','select:issued,returned,lost',true]]],
        'hostels' => ['title'=>'Hostels','table'=>'hostels','roles'=>$admin,'fields'=>[
            'name'=>['Hostel name','text',true], 'address'=>['Address','text',false]]],
        'hostel_rooms' => ['title'=>'Hostel rooms','table'=>'hostel_rooms','roles'=>$admin,'fields'=>[
            'hostel_id'=>['Hostel','ref:hostels:name',true], 'room_no'=>['Room no.','text',true], 'capacity'=>['Capacity','integer',true]]],
        'hostel_allocations' => ['title'=>'Hostel allocations','table'=>'hostel_allocations','roles'=>$admin,'fields'=>[
            'student_id'=>['Student','ref:students:full_name',true], 'room_id'=>['Room','ref:hostel_rooms:room_no',true],
            'starts_on'=>['Starts on','date',true], 'ends_on'=>['Ends on','date',false], 'status'=>['Status','select:active,ended',true]]],
        'transport_routes' => ['title'=>'Transport routes & stops','table'=>'transport_routes','roles'=>$admin,'fields'=>[
            'name'=>['Route name','text',true], 'stops'=>['Stops','textarea',false]]],
        'transport_vehicles' => ['title'=>'Transport vehicles','table'=>'transport_vehicles','roles'=>$admin,'fields'=>[
            'registration_no'=>['Registration no.','text',true], 'vehicle_type'=>['Vehicle type','text',true], 'capacity'=>['Capacity','integer',true]]],
        'transport_assignments' => ['title'=>'Transport assignments','table'=>'transport_assignments','roles'=>$admin,'fields'=>[
            'student_id'=>['Student','ref:students:full_name',true], 'route_id'=>['Route','ref:transport_routes:name',true],
            'vehicle_id'=>['Vehicle','ref:transport_vehicles:registration_no',false], 'stop_name'=>['Stop','text',false],
            'starts_on'=>['Starts on','date',true], 'status'=>['Status','select:active,ended',true]]],
    ];
}

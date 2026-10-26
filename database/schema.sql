-- College ERP schema (MySQL 8 / MariaDB 10.4+). Import into an empty database.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS transport_assignments, hostel_allocations, library_loans, exam_marks,
 payments, fee_charges, attendance, teacher_assignments, subjects, sections, classes,
 academic_years, staff, students, admissions, parent_students, users, transport_vehicles,
 transport_routes, hostel_rooms, hostels, books, exams, fee_types, colleges, universities;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE universities (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL,
 code VARCHAR(32) NOT NULL UNIQUE, address VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE colleges (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(160) NOT NULL, code VARCHAR(32) NOT NULL, address VARCHAR(255) NULL,
 phone VARCHAR(32) NULL, active TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_college_code (university_id,code),
 FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NULL,
 college_id BIGINT UNSIGNED NULL, full_name VARCHAR(160) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('super_admin','university_admin','college_admin','teacher','student','parent') NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1, failed_logins INT UNSIGNED NOT NULL DEFAULT 0,
 locked_until DATETIME NULL, last_login DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL,
 FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE SET NULL,
 INDEX idx_user_scope (university_id,college_id,role)
) ENGINE=InnoDB;
CREATE TABLE admissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL,
 college_id BIGINT UNSIGNED NOT NULL, application_no VARCHAR(40) NOT NULL,
 applicant_name VARCHAR(160) NOT NULL, email VARCHAR(190) NULL, phone VARCHAR(32) NULL,
 program VARCHAR(120) NOT NULL, application_date DATE NOT NULL, status ENUM('submitted','under_review','accepted','rejected','enrolled') NOT NULL DEFAULT 'submitted',
 notes TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_admission (college_id,application_no),
 FOREIGN KEY (university_id) REFERENCES universities(id), FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE students (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL, admission_no VARCHAR(40) NOT NULL, full_name VARCHAR(160) NOT NULL,
 email VARCHAR(190) NULL, phone VARCHAR(32) NULL, date_of_birth DATE NULL, gender VARCHAR(24) NULL,
 class_name VARCHAR(100) NULL, section_name VARCHAR(40) NULL, academic_year VARCHAR(20) NULL,
 address VARCHAR(255) NULL, status ENUM('active','graduated','withdrawn') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_student_adm (college_id,admission_no),
 UNIQUE KEY uq_student_user (user_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY (university_id) REFERENCES universities(id), FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE parent_students (
 parent_user_id BIGINT UNSIGNED NOT NULL, student_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(parent_user_id,student_id), FOREIGN KEY(parent_user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE staff (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL, employee_no VARCHAR(40) NOT NULL, full_name VARCHAR(160) NOT NULL,
 email VARCHAR(190) NULL, phone VARCHAR(32) NULL, job_title VARCHAR(100) NOT NULL,
 hire_date DATE NULL, status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 UNIQUE KEY uq_staff_no(college_id,employee_no), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE academic_years (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL,
 college_id BIGINT UNSIGNED NOT NULL, name VARCHAR(30) NOT NULL, starts_on DATE NOT NULL, ends_on DATE NOT NULL, active TINYINT(1) DEFAULT 0,
 UNIQUE KEY uq_year(college_id,name), FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE classes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL,
 college_id BIGINT UNSIGNED NOT NULL, name VARCHAR(80) NOT NULL, grade_level VARCHAR(40) NULL,
 UNIQUE KEY uq_class(college_id,name), FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE sections (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL,
 college_id BIGINT UNSIGNED NOT NULL, class_id BIGINT UNSIGNED NOT NULL, name VARCHAR(40) NOT NULL,
 UNIQUE KEY uq_section(class_id,name), FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE,
 FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE subjects (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL,
 college_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, code VARCHAR(32) NOT NULL,
 UNIQUE KEY uq_subject(college_id,code), FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE teacher_assignments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL,
 college_id BIGINT UNSIGNED NOT NULL, teacher_id BIGINT UNSIGNED NOT NULL, class_id BIGINT UNSIGNED NOT NULL, subject_id BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY uq_assignment(teacher_id,class_id,subject_id), FOREIGN KEY(university_id) REFERENCES universities(id),
 FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE, FOREIGN KEY(teacher_id) REFERENCES staff(id) ON DELETE CASCADE,
 FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE CASCADE, FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE attendance (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 student_id BIGINT UNSIGNED NOT NULL, attendance_date DATE NOT NULL, status ENUM('present','absent','late','excused') NOT NULL,
 remarks VARCHAR(255) NULL, recorded_by BIGINT UNSIGNED NULL, UNIQUE KEY uq_attendance(student_id,attendance_date),
 FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE,
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY(recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE fee_types (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(100) NOT NULL, default_amount DECIMAL(12,2) NOT NULL DEFAULT 0, due_date DATE NULL,
 UNIQUE KEY uq_fee_type(college_id,name), FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE fee_charges (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 student_id BIGINT UNSIGNED NOT NULL, fee_type_id BIGINT UNSIGNED NULL, description VARCHAR(160) NOT NULL,
 amount DECIMAL(12,2) NOT NULL, due_date DATE NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE,
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY(fee_type_id) REFERENCES fee_types(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE payments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 charge_id BIGINT UNSIGNED NOT NULL, student_id BIGINT UNSIGNED NOT NULL, receipt_no VARCHAR(48) NOT NULL,
 amount DECIMAL(12,2) NOT NULL, paid_on DATE NOT NULL, method ENUM('cash','card','bank_transfer','online','other') NOT NULL,
 reference VARCHAR(100) NULL, received_by BIGINT UNSIGNED NULL, UNIQUE KEY uq_receipt(college_id,receipt_no),
 FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE,
 FOREIGN KEY(charge_id) REFERENCES fee_charges(id) ON DELETE RESTRICT, FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
 FOREIGN KEY(received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE exams (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL, class_name VARCHAR(100) NOT NULL, exam_date DATE NOT NULL, max_marks DECIMAL(7,2) NOT NULL DEFAULT 100,
 published TINYINT(1) NOT NULL DEFAULT 0, UNIQUE KEY uq_exam(college_id,name,class_name),
 FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE exam_marks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 exam_id BIGINT UNSIGNED NOT NULL, student_id BIGINT UNSIGNED NOT NULL, subject VARCHAR(120) NOT NULL, marks DECIMAL(7,2) NOT NULL,
 max_marks DECIMAL(7,2) NOT NULL DEFAULT 100, grade VARCHAR(8) NULL, entered_by BIGINT UNSIGNED NULL,
 UNIQUE KEY uq_mark(exam_id,student_id,subject), FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE,
 FOREIGN KEY(exam_id) REFERENCES exams(id) ON DELETE CASCADE, FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
 FOREIGN KEY(entered_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE books (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 isbn VARCHAR(32) NULL, title VARCHAR(180) NOT NULL, author VARCHAR(160) NULL, category VARCHAR(100) NULL, copies INT UNSIGNED NOT NULL DEFAULT 1,
 FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE library_loans (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 book_id BIGINT UNSIGNED NOT NULL, student_id BIGINT UNSIGNED NOT NULL, issued_on DATE NOT NULL, due_on DATE NOT NULL,
 returned_on DATE NULL, status ENUM('issued','returned','lost') NOT NULL DEFAULT 'issued',
 FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE,
 FOREIGN KEY(book_id) REFERENCES books(id), FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE hostels (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL, address VARCHAR(255) NULL, UNIQUE KEY uq_hostel(college_id,name),
 FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE hostel_rooms (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 hostel_id BIGINT UNSIGNED NOT NULL, room_no VARCHAR(32) NOT NULL, capacity INT UNSIGNED NOT NULL DEFAULT 1,
 UNIQUE KEY uq_room(hostel_id,room_no), FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE,
 FOREIGN KEY(hostel_id) REFERENCES hostels(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE hostel_allocations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 student_id BIGINT UNSIGNED NOT NULL, room_id BIGINT UNSIGNED NOT NULL, starts_on DATE NOT NULL, ends_on DATE NULL, status ENUM('active','ended') NOT NULL DEFAULT 'active',
 FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE,
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY(room_id) REFERENCES hostel_rooms(id)
) ENGINE=InnoDB;
CREATE TABLE transport_routes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL, stops TEXT NULL, UNIQUE KEY uq_route(college_id,name),
 FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE transport_vehicles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 registration_no VARCHAR(32) NOT NULL, vehicle_type VARCHAR(80) NOT NULL, capacity INT UNSIGNED NOT NULL,
 UNIQUE KEY uq_vehicle(college_id,registration_no), FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE transport_assignments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, university_id BIGINT UNSIGNED NOT NULL, college_id BIGINT UNSIGNED NOT NULL,
 student_id BIGINT UNSIGNED NOT NULL, route_id BIGINT UNSIGNED NOT NULL, vehicle_id BIGINT UNSIGNED NULL, stop_name VARCHAR(120) NULL,
 starts_on DATE NOT NULL, status ENUM('active','ended') NOT NULL DEFAULT 'active',
 FOREIGN KEY(university_id) REFERENCES universities(id), FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE,
 FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY(route_id) REFERENCES transport_routes(id),
 FOREIGN KEY(vehicle_id) REFERENCES transport_vehicles(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO universities(name,code,address) VALUES ('Sample University','SAMPLE-U','Replace with your university address');
INSERT INTO colleges(university_id,name,code,address) VALUES
(1,'North College','NORTH','North campus'),(1,'South College','SOUTH','South campus');
-- Fictional sample records (no users, credentials, personal contact details, or secrets).
INSERT INTO admissions(university_id,college_id,application_no,applicant_name,email,phone,program,application_date,status)
VALUES (1,1,'APP-SAMPLE-001','Sample Applicant','applicant@example.invalid',NULL,'Bachelor of Arts','2026-06-01','under_review');
INSERT INTO students(university_id,college_id,admission_no,full_name,email,phone,class_name,section_name,academic_year,status) VALUES
(1,1,'STU-SAMPLE-001','Sample Student One',NULL,NULL,'Year 1','A','2026-27','active'),
(1,2,'STU-SAMPLE-002','Sample Student Two',NULL,NULL,'Year 1','A','2026-27','active');
INSERT INTO staff(university_id,college_id,employee_no,full_name,job_title,status) VALUES
(1,1,'EMP-SAMPLE-001','Sample Teacher','Teacher','active'),(1,2,'EMP-SAMPLE-002','Sample Staff','Administrator','active');
INSERT INTO academic_years(university_id,college_id,name,starts_on,ends_on,active) VALUES
(1,1,'2026-27','2026-06-01','2027-05-31',1),(1,2,'2026-27','2026-06-01','2027-05-31',1);
INSERT INTO classes(university_id,college_id,name,grade_level) VALUES (1,1,'Year 1','Undergraduate'),(1,2,'Year 1','Undergraduate');
INSERT INTO sections(university_id,college_id,class_id,name) VALUES (1,1,1,'A'),(1,2,2,'A');
INSERT INTO subjects(university_id,college_id,name,code) VALUES (1,1,'Foundations','FOUND-101'),(1,2,'Introduction to Science','SCI-101');
INSERT INTO teacher_assignments(university_id,college_id,teacher_id,class_id,subject_id) VALUES (1,1,1,1,1);
INSERT INTO attendance(university_id,college_id,student_id,attendance_date,status,remarks) VALUES
(1,1,1,'2026-09-01','present','Sample attendance'),(1,2,2,'2026-09-01','late','Sample attendance');
INSERT INTO fee_types(university_id,college_id,name,default_amount,due_date) VALUES (1,1,'Tuition','500.00','2026-10-15');
INSERT INTO fee_charges(university_id,college_id,student_id,fee_type_id,description,amount,due_date) VALUES (1,1,1,1,'Sample tuition charge','500.00','2026-10-15');
INSERT INTO payments(university_id,college_id,charge_id,student_id,receipt_no,amount,paid_on,method,reference) VALUES
(1,1,1,1,'SAMPLE-RECEIPT-001','200.00','2026-09-05','cash','Non-production sample');
INSERT INTO exams(university_id,college_id,name,class_name,exam_date,max_marks,published) VALUES
(1,1,'Mid-term Sample','Year 1','2026-10-10',100,1);
INSERT INTO exam_marks(university_id,college_id,exam_id,student_id,subject,marks,max_marks,grade) VALUES
(1,1,1,1,'Foundations',85,100,'B');
INSERT INTO books(university_id,college_id,isbn,title,author,category,copies) VALUES
(1,1,'SAMPLE-ISBN-001','Sample Academic Handbook','Sample Author','Reference',3);
INSERT INTO library_loans(university_id,college_id,book_id,student_id,issued_on,due_on,status) VALUES
(1,1,1,1,'2026-09-01','2026-09-15','issued');
INSERT INTO hostels(university_id,college_id,name,address) VALUES (1,1,'Sample Hall','North campus');
INSERT INTO hostel_rooms(university_id,college_id,hostel_id,room_no,capacity) VALUES (1,1,1,'A-101',2);
INSERT INTO hostel_allocations(university_id,college_id,student_id,room_id,starts_on,status) VALUES (1,1,1,1,'2026-08-01','active');
INSERT INTO transport_routes(university_id,college_id,name,stops) VALUES (1,1,'North Loop','Main Gate; Library; North College');
INSERT INTO transport_vehicles(university_id,college_id,registration_no,vehicle_type,capacity) VALUES (1,1,'SAMPLE-0001','Bus',30);
INSERT INTO transport_assignments(university_id,college_id,student_id,route_id,vehicle_id,stop_name,starts_on,status) VALUES
(1,1,1,1,1,'Library Stop','2026-08-01','active');

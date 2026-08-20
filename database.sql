-- Create Database
CREATE DATABASE IF NOT EXISTS school_result;
USE school_result;

-- 1. School Settings Table
CREATE TABLE school_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_name VARCHAR(255) NOT NULL,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(100),
    website VARCHAR(100),
    logo_path VARCHAR(255) DEFAULT 'assets/images/logo.png',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO school_settings (school_name, address, phone, email, website) 
VALUES ('Excellence International Academy', '123 Education Boulevard, Tech District', '+1 234 567 8900', 'info@excellenceacademy.edu', 'www.excellenceacademy.edu');

-- 2. Admins Table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default Admin: admin@school.com / password: password
INSERT INTO admins (name, email, password) 
VALUES ('System Administrator', 'admin@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- 3. Academic Sessions
CREATE TABLE academic_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(50) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB;

INSERT INTO academic_sessions (session_name) VALUES ('2023-2024'), ('2024-2025');

-- 4. Classes
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

INSERT INTO classes (class_name) VALUES ('Class 8'), ('Class 9'), ('Class 10');

-- 5. Examinations
CREATE TABLE examinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_name VARCHAR(100) NOT NULL,
    session_id INT NOT NULL,
    status ENUM('draft', 'published', 'unpublished') DEFAULT 'published',
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id)
) ENGINE=InnoDB;

INSERT INTO examinations (exam_name, session_id, status) VALUES ('Final Examination', 1, 'published');

-- 6. Students
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    roll_number INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    class_id INT NOT NULL,
    session_id INT NOT NULL,
    dob DATE,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id),
    UNIQUE KEY unique_roll (class_id, session_id, roll_number)
) ENGINE=InnoDB;

INSERT INTO students (student_id, roll_number, name, class_id, session_id, dob) 
VALUES ('STU-2024-001', 1, 'John Doe', 3, 1, '2008-05-15');

-- 7. Subjects
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20) NOT NULL,
    full_marks INT DEFAULT 100,
    pass_marks INT DEFAULT 33,
    class_id INT NOT NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id)
) ENGINE=InnoDB;

INSERT INTO subjects (subject_name, subject_code, full_marks, pass_marks, class_id) VALUES 
('Bangla', 'BAN101', 100, 33, 3),
('English', 'ENG101', 100, 33, 3),
('Mathematics', 'MAT101', 100, 33, 3),
('Science', 'SCI101', 100, 33, 3);

-- 8. Marks
CREATE TABLE marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    subject_id INT NOT NULL,
    obtained_marks INT NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (exam_id) REFERENCES examinations(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    UNIQUE KEY unique_mark (student_id, exam_id, subject_id)
) ENGINE=InnoDB;

INSERT INTO marks (student_id, exam_id, subject_id, obtained_marks) VALUES 
(1, 1, 1, 85), (1, 1, 2, 78), (1, 1, 3, 92), (1, 1, 4, 88);

-- 9. Grade System
CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade_name VARCHAR(5) NOT NULL,
    gpa DECIMAL(3,2) NOT NULL,
    min_marks INT NOT NULL,
    max_marks INT NOT NULL
) ENGINE=InnoDB;

INSERT INTO grades (grade_name, gpa, min_marks, max_marks) VALUES 
('A+', 5.00, 80, 100),
('A', 4.00, 70, 79),
('A-', 3.50, 60, 69),
('B', 3.00, 50, 59),
('C', 2.00, 40, 49),
('D', 1.00, 33, 39),
('F', 0.00, 0, 32);
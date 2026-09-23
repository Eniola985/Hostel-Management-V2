SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS payments, allocations, applications, rooms, hostels, students, admin;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(150),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO admin (username, password, email) VALUES 
('admin', 'admin123', 'admin@polyibadan.edu.ng');

CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    matric_no VARCHAR(13) NOT NULL UNIQUE,
    full_name VARCHAR(200) NOT NULL,
    department VARCHAR(150) NOT NULL,
    level VARCHAR(20) NOT NULL,
    gender VARCHAR(10) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(150),
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO students (matric_no, full_name, department, level, gender, phone, email, password) VALUES
('2024705010001', 'Adewale Bamidele', 'Computer Science', 'ND1', 'Male', '08031234567', 'adewale@student.edu', 'student123'),
('2024705010002', 'Funmilayo Okafor', 'Computer Science', 'ND2', 'Female', '08041234568', 'funmilayo@student.edu', 'student123'),
('2024705010003', 'Ibrahim Yusuf', 'Computer Science', 'HND1', 'Male', '08051234569', 'ibrahim@student.edu', 'student123'),
('2024705010004', 'Chiamaka Nwosu', 'Computer Science', 'HND2', 'Female', '08061234570', 'chiamaka@student.edu', 'student123'),
('2024705010005', 'Kazeem Oladipo', 'Electrical/Electronics', 'ND1', 'Male', '08071234571', 'kazeem@student.edu', 'student123'),
('2024705010006', 'Aisha Bello', 'Electrical/Electronics', 'ND2', 'Female', '08081234572', 'aisha@student.edu', 'student123'),
('2024705010007', 'Tunde Akinola', 'Electrical/Electronics', 'HND1', 'Male', '08091234573', 'tunde@student.edu', 'student123'),
('2024705010008', 'Blessing Danjuma', 'Electrical/Electronics', 'HND2', 'Female', '08101234574', 'blessing@student.edu', 'student123'),
('2024705010009', 'Sade Adebayo', 'Accounting', 'ND1', 'Female', '08111234575', 'sade@student.edu', 'student123'),
('2024705010010', 'Musa Abdullahi', 'Accounting', 'ND2', 'Male', '08121234576', 'musa@student.edu', 'student123'),
('2024705010011', 'Ruth Okafor', 'Accounting', 'HND1', 'Female', '08131234577', 'ruth@student.edu', 'student123'),
('2024705010012', 'Emmanuel Ojo', 'Accounting', 'HND2', 'Male', '08141234578', 'emmanuel@student.edu', 'student123'),
('2024705010013', 'Tobi Adesina', 'Mass Communication', 'ND1', 'Male', '08151234579', 'tobi@student.edu', 'student123'),
('2024705010014', 'Grace Eze', 'Mass Communication', 'ND2', 'Female', '08161234580', 'grace@student.edu', 'student123'),
('2024705010015', 'Damilola Folarin', 'Mass Communication', 'HND1', 'Female', '08171234581', 'damilola@student.edu', 'student123'),
('2024705010016', 'Babatunde Salami', 'Mass Communication', 'HND2', 'Male', '08181234582', 'babatunde@student.edu', 'student123'),
('2024705010017', 'Hassan Lawal', 'Civil Engineering', 'ND1', 'Male', '08191234583', 'hassan@student.edu', 'student123'),
('2024705010018', 'Zainab Musa', 'Science Laboratory Technology', 'ND1', 'Female', '08191234584', 'zainab@student.edu', 'student123');

CREATE TABLE hostels (
    hostel_id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_name VARCHAR(150) NOT NULL,
    hostel_type VARCHAR(10) NOT NULL,
    total_rooms INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO hostels (hostel_name, hostel_type, total_rooms) VALUES
('Olori Hostel', 'Female', 50),
('Unity Hall', 'Male', 60),
('Orisun Hostel', 'Male', 45),
('Ramat Hostel', 'Male', 50);

CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT NOT NULL,
    room_number VARCHAR(50) NOT NULL,
    capacity INT NOT NULL DEFAULT 4,
    occupied INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'Available',
    FOREIGN KEY (hostel_id) REFERENCES hostels(hostel_id) ON DELETE CASCADE
);

INSERT IGNORE INTO rooms (hostel_id, room_number, capacity, occupied, status) VALUES
(1,'101',4,0,'Available'),(1,'102',4,0,'Available'),(1,'103',4,0,'Available'),(1,'104',4,0,'Available'),(1,'105',4,0,'Available'),
(2,'101',4,0,'Available'),(2,'102',4,0,'Available'),(2,'103',4,0,'Available'),(2,'104',4,0,'Available'),(2,'105',4,0,'Available'),
(3,'101',4,0,'Available'),(3,'102',4,0,'Available'),(3,'103',4,0,'Available'),(3,'104',4,0,'Available'),(3,'105',4,0,'Available'),
(4,'101',4,0,'Available'),(4,'102',4,0,'Available'),(4,'103',4,0,'Available'),(4,'104',4,0,'Available'),(4,'105',4,0,'Available');

CREATE TABLE applications (
    app_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    hostel_id INT NOT NULL,
    preferred_room_id INT NULL,
    preferred_bunk VARCHAR(10) NULL,
    payment_ref VARCHAR(100),
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    rejection_reason TEXT NULL,
    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (hostel_id) REFERENCES hostels(hostel_id) ON DELETE CASCADE,
    FOREIGN KEY (preferred_room_id) REFERENCES rooms(room_id) ON DELETE SET NULL
);

CREATE TABLE allocations (
    allocation_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    room_id INT NOT NULL,
    bunk_number VARCHAR(10) NULL,
    allocation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE
);

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_ref VARCHAR(100),
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    verified VARCHAR(10) DEFAULT 'No',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

INSERT IGNORE INTO payments (payment_id, student_id, amount, payment_ref, verified) VALUES
(1, 1, 25000.00, 'TRF202400001', 'Yes'),
(2, 2, 25000.00, 'TRF202400002', 'No'),
(3, 3, 25000.00, 'TRF202400003', 'Yes'),
(4, 4, 25000.00, 'TRF202400004', 'No'),
(5, 5, 25000.00, 'TRF202400005', 'Yes'),
(6, 6, 25000.00, 'TRF202400006', 'No'),
(7, 7, 25000.00, 'TRF202400007', 'Yes'),
(8, 8, 25000.00, 'TRF202400008', 'No');

DELETE FROM allocations;
DELETE FROM applications;
UPDATE rooms SET occupied = 0;

INSERT INTO applications (student_id, hostel_id, payment_ref, status) VALUES
(1, 2, 'PAY-1', 'Pending'),
(6, 1, 'PAY-6', 'Pending'),
(9, 4, 'PAY-9', 'Pending'),
(3, 3, 'PAY-3', 'Pending'),
(18, 1, 'PAY-18', 'Pending');

INSERT INTO allocations (student_id, room_id, status) VALUES
(2, 1, 'Active'),
(7, 6, 'Active'),
(9, 17, 'Active'),
(10, 12, 'Active'),
(13, 7, 'Active'),
(18, 18, 'Active');

UPDATE rooms SET occupied = CASE room_id
    WHEN 1 THEN 1
    WHEN 6 THEN 1
    WHEN 17 THEN 1
    WHEN 12 THEN 1
    WHEN 7 THEN 1
    WHEN 18 THEN 1
    ELSE 0
END;

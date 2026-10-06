SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS admin_notifications, password_reset_tokens, hostel_admins, payments, allocations, applications, rooms, hostels, students, admin;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE hostels (
    hostel_id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_name VARCHAR(150) NOT NULL UNIQUE,
    hostel_type ENUM('Male','Female') NOT NULL,
    total_rooms INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO hostels (hostel_name, hostel_type, total_rooms) VALUES
('Olori Hostel','Female',50),
('Ramat Hostel','Female',50),
('Ramat Extension','Female',50),
('Unity Hall','Male',60),
('Orisun Hostel','Male',45);

CREATE TABLE hostel_admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT NOT NULL UNIQUE,
    username VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(150) NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    FOREIGN KEY (hostel_id) REFERENCES hostels(hostel_id) ON DELETE CASCADE
);

INSERT INTO hostel_admins (hostel_id, username, password_hash) VALUES
(1,'Olori Hostel','\$2y\$12\$LEESWXDQGWMdj1MBHaIGBeMP.qCZzB1mFJWG7V0YJR/w6IZKWUl8u'),
(2,'Ramat Hostel','\$2y\$12\$LEESWXDQGWMdj1MBHaIGBeMP.qCZzB1mFJWG7V0YJR/w6IZKWUl8u'),
(3,'Ramat Extension','\$2y\$12\$LEESWXDQGWMdj1MBHaIGBeMP.qCZzB1mFJWG7V0YJR/w6IZKWUl8u'),
(4,'Unity Hall','\$2y\$12\$LEESWXDQGWMdj1MBHaIGBeMP.qCZzB1mFJWG7V0YJR/w6IZKWUl8u'),
(5,'Orisun Hostel','\$2y\$12\$LEESWXDQGWMdj1MBHaIGBeMP.qCZzB1mFJWG7V0YJR/w6IZKWUl8u');

CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    matric_no VARCHAR(13) NULL UNIQUE,
    form_no VARCHAR(30) NULL UNIQUE,
    full_name VARCHAR(200) NOT NULL,
    department VARCHAR(150) NOT NULL,
    level VARCHAR(20) NOT NULL,
    gender ENUM('Male','Female') NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO students (matric_no, form_no, full_name, department, level, gender, phone, email, password) VALUES
('2024705010001','D2405001','Adewale Bamidele','Computer Science','ND1','Male','08031234567','adewale@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010002','D2405002','Funmilayo Okafor','Computer Science','ND2','Female','08041234568','funmilayo@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010003','F2405003','Ibrahim Yusuf','Computer Science','HND1','Male','08051234569','ibrahim@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010004','D2405004','Chiamaka Nwosu','Business Administration','HND2','Female','08061234570','chiamaka@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010005','D2405005','Tunde Bakare','Civil Engineering','ND1','Male','08071234571','tunde@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010006','F2405006','Zainab Danjuma','Mass Communication','ND2','Female','08081234572','zainab@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010007','D2405007','Emeka Obi','Architecture','HND1','Male','08091234573','emeka@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010008','D2405008','Blessing Adeyemi','Accountancy','HND2','Female','08011234574','blessing@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010009','F2405009','Oluwaseun Adeleke','Mechanical Engineering','ND1','Male','08021234575','seun@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010010','D2405010','Amina Bello','Science Laboratory Tech','ND2','Female','08031234576','amina@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010011','D2405011','Kazeem Oladipo','Urban & Regional Planning','HND1','Male','08041234577','kazeem@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010012','F2405012','Precious Eze','Banking & Finance','HND2','Female','08051234578','precious@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010013','D2405013','Samuel Afolabi','Estate Management','ND1','Male','08061234579','samuel@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010014','D2405014','Halimat Sadiq','Marketing','ND2','Female','08071234580','halimat@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010015','F2405015','Victor Chukwu','Computer Engineering','HND1','Male','08081234581','victor@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010016','D2405016','Khadijah Aliyu','Public Administration','HND2','Female','08091234582','khadijah@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010017','D2405017','Babajide Sanwo','Quantity Surveying','ND1','Male','08011234583','babajide@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010018','F2405018','Omolara Ajayi','Food Technology','ND2','Female','08021234584','omolara@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010019','D2405019','Gideon Okoro','Electrical Engineering','HND1','Male','08031234585','gideon@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010020','D2405020','Rukayat Jimoh','Statistics','HND2','Female','08041234586','rukayat@student.edu','$2y$12$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze');

CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT NOT NULL,
    room_number VARCHAR(50) NOT NULL,
    capacity INT NOT NULL DEFAULT 4,
    occupied INT NOT NULL DEFAULT 0,
    status ENUM('Available','Full') NOT NULL DEFAULT 'Available',
    UNIQUE KEY uq_hostel_room (hostel_id, room_number),
    FOREIGN KEY (hostel_id) REFERENCES hostels(hostel_id) ON DELETE CASCADE
);

CREATE TABLE applications (
    app_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    hostel_id INT NOT NULL,
    preferred_room_id INT NULL,
    preferred_bunk VARCHAR(10) NULL,
    payment_ref VARCHAR(100) NULL,
    status ENUM('Pending','Approved','Rejected','Allocated') NOT NULL DEFAULT 'Pending',
    rejection_reason TEXT NULL,
    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (hostel_id) REFERENCES hostels(hostel_id) ON DELETE CASCADE,
    FOREIGN KEY (preferred_room_id) REFERENCES rooms(room_id) ON DELETE SET NULL,
    UNIQUE KEY uq_application_student (student_id),
    INDEX idx_app_hostel_status (hostel_id,status),
    INDEX idx_app_student (student_id)
);

CREATE TABLE allocations (
    allocation_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    room_id INT NOT NULL,
    bunk_number VARCHAR(10) NULL,
    allocation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Active','Vacated') NOT NULL DEFAULT 'Active',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    INDEX idx_alloc_room_status (room_id,status),
    INDEX idx_alloc_student_status (student_id,status)
);

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_ref VARCHAR(100) NOT NULL UNIQUE,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    verified ENUM('Yes','No') NOT NULL DEFAULT 'No',
    verification_source VARCHAR(50) NULL,
    verified_at DATETIME NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_payment_student (student_id)
);

CREATE TABLE password_reset_tokens (
    reset_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_reset_student (student_id),
    INDEX idx_reset_expiry (expires_at)
);

CREATE TABLE admin_notifications (
    notification_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT NOT NULL,
    application_id INT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'new_application',
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    FOREIGN KEY (hostel_id) REFERENCES hostels(hostel_id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(app_id) ON DELETE CASCADE,
    INDEX idx_admin_notification_scope (hostel_id,is_read,created_at)
);

-- Seed rooms: 5 rooms per hostel for development/testing.
INSERT INTO rooms (hostel_id, room_number, capacity) VALUES
(1,'101',4),(1,'102',4),(1,'103',4),(1,'104',4),(1,'105',4),
(2,'101',4),(2,'102',4),(2,'103',4),(2,'104',4),(2,'105',4),
(3,'101',4),(3,'102',4),(3,'103',4),(3,'104',4),(3,'105',4),
(4,'101',4),(4,'102',4),(4,'103',4),(4,'104',4),(4,'105',4),
(5,'101',4),(5,'102',4),(5,'103',4),(5,'104',4),(5,'105',4);

-- Seed Applications for all hostels and statuses
INSERT INTO applications (student_id, hostel_id, preferred_room_id, preferred_bunk, payment_ref, status, applied_at) VALUES
(1, 4, 16, 'Bunk 1 (Lower)', 'RRR-2024-001', 'Allocated', '2024-02-01 10:30:00'),
(2, 1, 1, 'Bunk 1 (Lower)', 'RRR-2024-002', 'Allocated', '2024-02-01 12:00:00'),
(3, 4, 16, 'Bunk 1 (Upper)', 'RRR-2024-003', 'Approved', '2024-02-02 09:30:00'),
(4, 1, 1, 'Bunk 1 (Upper)', 'RRR-2024-004', 'Allocated', '2024-02-02 14:20:00'),
(5, 4, 17, 'Bunk 1 (Lower)', 'RRR-2024-005', 'Pending', '2024-02-03 08:45:00'),
(6, 2, 6, 'Bunk 1 (Lower)', 'RRR-2024-006', 'Pending', '2024-02-03 10:00:00'),
(7, 4, 17, 'Bunk 1 (Upper)', 'RRR-2024-007', 'Rejected', '2024-02-04 11:15:00'),
(8, 1, 2, 'Bunk 1 (Lower)', 'RRR-2024-008', 'Approved', '2024-02-04 13:40:00'),
(9, 5, 21, 'Bed 1', 'RRR-2024-009', 'Pending', '2024-02-05 10:30:00'),
(10, 2, 6, 'Bunk 1 (Upper)', 'RRR-2024-010', 'Allocated', '2024-02-05 15:45:00'),
(11, 4, 18, 'Bunk 1 (Lower)', 'RRR-2024-011', 'Approved', '2024-02-06 09:00:00'),
(12, 1, 2, 'Bunk 1 (Upper)', 'RRR-2024-012', 'Pending', '2024-02-06 12:30:00'),
(13, 5, 21, 'Bed 2', 'RRR-2024-013', 'Allocated', '2024-02-07 09:20:00'),
(14, 3, 11, 'Bunk 1 (Lower)', 'RRR-2024-014', 'Approved', '2024-02-07 14:30:00'),
(15, 4, 18, 'Bunk 1 (Upper)', 'RRR-2024-015', 'Pending', '2024-02-08 10:00:00'),
(16, 1, 3, 'Bunk 1 (Lower)', 'RRR-2024-016', 'Allocated', '2024-02-08 13:00:00'),
(17, 4, 19, 'Bunk 1 (Lower)', 'RRR-2024-017', 'Approved', '2024-02-09 09:15:00'),
(18, 2, 7, 'Bunk 1 (Lower)', 'RRR-2024-018', 'Approved', '2024-02-09 11:30:00'),
(19, 4, 19, 'Bunk 1 (Upper)', 'RRR-2024-019', 'Allocated', '2024-02-10 10:00:00'),
(20, 3, 11, 'Bunk 1 (Upper)', 'RRR-2024-020', 'Pending', '2024-02-10 14:00:00');

-- Seed Allocations
INSERT INTO allocations (student_id, room_id, bunk_number, allocation_date, status) VALUES
(1, 16, 'Bunk 1 (Lower)', '2024-02-01 11:00:00', 'Active'),
(2, 1, 'Bunk 1 (Lower)', '2024-02-01 12:30:00', 'Active'),
(4, 1, 'Bunk 1 (Upper)', '2024-02-02 15:00:00', 'Active'),
(10, 6, 'Bunk 1 (Upper)', '2024-02-05 16:00:00', 'Active'),
(13, 21, 'Bed 2', '2024-02-07 10:00:00', 'Active'),
(16, 3, 'Bunk 1 (Lower)', '2024-02-08 13:30:00', 'Active'),
(19, 19, 'Bunk 1 (Upper)', '2024-02-10 10:30:00', 'Active');

-- Seed Payments
INSERT INTO payments (student_id, amount, payment_ref, verified, verification_source, verified_at) VALUES
(1, 30000.00, 'RRR-2024-001', 'Yes', 'Remita API', '2024-02-01 10:35:00'),
(2, 30000.00, 'RRR-2024-002', 'Yes', 'Remita API', '2024-02-01 12:05:00'),
(3, 30000.00, 'RRR-2024-003', 'Yes', 'Remita API', '2024-02-02 09:35:00'),
(4, 30000.00, 'RRR-2024-004', 'Yes', 'Remita API', '2024-02-02 14:25:00'),
(5, 30000.00, 'RRR-2024-005', 'Yes', 'Remita API', '2024-02-03 08:50:00'),
(6, 30000.00, 'RRR-2024-006', 'Yes', 'Remita API', '2024-02-03 10:05:00'),
(7, 30000.00, 'RRR-2024-007', 'Yes', 'Remita API', '2024-02-04 11:20:00'),
(8, 30000.00, 'RRR-2024-008', 'Yes', 'Remita API', '2024-02-04 13:45:00'),
(9, 30000.00, 'RRR-2024-009', 'Yes', 'Remita API', '2024-02-05 10:35:00'),
(10, 30000.00, 'RRR-2024-010', 'Yes', 'Remita API', '2024-02-05 15:50:00'),
(11, 30000.00, 'RRR-2024-011', 'Yes', 'Remita API', '2024-02-06 09:05:00'),
(12, 30000.00, 'RRR-2024-012', 'Yes', 'Remita API', '2024-02-06 12:35:00'),
(13, 30000.00, 'RRR-2024-013', 'Yes', 'Remita API', '2024-02-07 09:25:00'),
(14, 30000.00, 'RRR-2024-014', 'Yes', 'Remita API', '2024-02-07 14:35:00'),
(15, 30000.00, 'RRR-2024-015', 'Yes', 'Remita API', '2024-02-08 10:05:00'),
(16, 30000.00, 'RRR-2024-016', 'Yes', 'Remita API', '2024-02-08 13:05:00'),
(17, 30000.00, 'RRR-2024-017', 'Yes', 'Remita API', '2024-02-09 09:20:00'),
(18, 30000.00, 'RRR-2024-018', 'Yes', 'Remita API', '2024-02-09 11:35:00'),
(19, 30000.00, 'RRR-2024-019', 'Yes', 'Remita API', '2024-02-10 10:05:00'),
(20, 30000.00, 'RRR-2024-020', 'Yes', 'Remita API', '2024-02-10 14:05:00');

-- Update room occupancy based on active allocations
UPDATE rooms r SET occupied = (
    SELECT COUNT(*) FROM allocations a WHERE a.room_id = r.room_id AND a.status = 'Active'
);

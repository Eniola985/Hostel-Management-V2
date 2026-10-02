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
('2024705010001',NULL,'Adewale Bamidele','Computer Science','ND1','Male','08031234567','adewale@student.edu','\$2y\$12\$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010002',NULL,'Funmilayo Okafor','Computer Science','ND2','Female','08041234568','funmilayo@student.edu','\$2y\$12\$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010003',NULL,'Ibrahim Yusuf','Computer Science','HND1','Male','08051234569','ibrahim@student.edu','\$2y\$12\$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze'),
('2024705010004',NULL,'Chiamaka Nwosu','Computer Science','HND2','Female','08061234570','chiamaka@student.edu','\$2y\$12\$L3U7S.G4MYZQTChtFqZcwe16rf44nIH1YAz3ty4tZXF4wBm2PbHze');

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

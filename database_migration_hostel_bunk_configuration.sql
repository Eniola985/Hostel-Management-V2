ALTER TABLE hostels
ADD COLUMN uses_bunks TINYINT(1) NOT NULL DEFAULT 1 AFTER hostel_type;

UPDATE hostels
SET uses_bunks = 0
WHERE hostel_name = 'Unity Hall';

UPDATE hostels
SET uses_bunks = 1
WHERE hostel_name IN ('Olori Hostel', 'Ramat Hostel', 'Ramat Extension', 'Orisun Hostel');

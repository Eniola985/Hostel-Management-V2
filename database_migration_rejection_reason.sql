-- Run this once on an existing hostel database before deploying the PHP changes.
ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER status;

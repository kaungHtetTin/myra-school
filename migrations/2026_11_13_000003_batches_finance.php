<?php
return ['up' => [
    "ALTER TABLE classes ADD fee DECIMAL(12,2) NOT NULL DEFAULT 0",
    "ALTER TABLE students MODIFY class_id INT UNSIGNED NULL, MODIFY gender VARCHAR(20) NULL, MODIFY dob DATE NULL, MODIFY guardian VARCHAR(100) NULL, ADD origin VARCHAR(20) NOT NULL DEFAULT 'Other', ADD account VARCHAR(255) NULL",
    "CREATE TABLE batches (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, class_id INT UNSIGNED NOT NULL, name VARCHAR(100) NOT NULL, capacity INT NOT NULL DEFAULT 30, starts_on DATE NOT NULL, ends_on DATE NULL, status VARCHAR(20) NOT NULL DEFAULT 'Open', UNIQUE KEY class_batch(class_id,name), FOREIGN KEY (class_id) REFERENCES classes(id)) ENGINE=InnoDB",
    "CREATE TABLE enrollments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, batch_id INT UNSIGNED NOT NULL, student_id INT UNSIGNED NOT NULL, enrolled_on DATE NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Active', UNIQUE KEY batch_student(batch_id,student_id), FOREIGN KEY (batch_id) REFERENCES batches(id), FOREIGN KEY (student_id) REFERENCES students(id)) ENGINE=InnoDB",
    "ALTER TABLE invoices MODIFY description VARCHAR(255) NOT NULL, ADD enrollment_id INT UNSIGNED NULL, ADD CONSTRAINT fk_invoices_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id)",
    "CREATE TABLE finance_entries (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, type VARCHAR(10) NOT NULL, description VARCHAR(150) NOT NULL, amount DECIMAL(12,2) NOT NULL, occurred_on DATE NOT NULL, category VARCHAR(100) NOT NULL) ENGINE=InnoDB",
    "INSERT INTO batches (class_id,name,capacity,starts_on) SELECT c.id,'Existing students',GREATEST(c.capacity,(SELECT COUNT(*) FROM students s WHERE s.class_id=c.id)),COALESCE((SELECT MIN(enrolled_on) FROM students s WHERE s.class_id=c.id),CURRENT_DATE) FROM classes c WHERE EXISTS (SELECT 1 FROM students s WHERE s.class_id=c.id)",
    "INSERT INTO enrollments (batch_id,student_id,enrolled_on,status) SELECT b.id,s.id,s.enrolled_on,IF(s.status='Active','Active','Completed') FROM students s JOIN batches b ON b.class_id=s.class_id",
    "UPDATE invoices i JOIN enrollments e ON e.student_id=i.student_id SET i.enrollment_id=e.id"
], 'down' => [
    "DROP TABLE finance_entries", "ALTER TABLE invoices DROP FOREIGN KEY fk_invoices_enrollment", "ALTER TABLE invoices DROP enrollment_id", "DROP TABLE enrollments", "DROP TABLE batches", "ALTER TABLE students DROP origin, DROP account", "ALTER TABLE classes DROP fee"
]];

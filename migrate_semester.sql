-- Run once on the online_enroll database to store the semester with each enrollment request.
ALTER TABLE `student_subject` ADD COLUMN `semester` varchar(20) NOT NULL DEFAULT '';

-- Database: moor_portal
CREATE DATABASE IF NOT EXISTS `moor_portal` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `moor_portal`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `jamb_no` VARCHAR(50) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `status` ENUM('prospective','new','returning','pending','completed') DEFAULT 'prospective',
  `o_level_path` VARCHAR(255) DEFAULT NULL,
  `jamb_path` VARCHAR(255) DEFAULT NULL,
  `post_utme_path` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `programs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `program_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `course_code` VARCHAR(50) DEFAULT NULL,
  `credits` INT DEFAULT 3,
  `instructor` VARCHAR(255) DEFAULT NULL,
  `schedule` VARCHAR(255) DEFAULT NULL,
  `room` VARCHAR(100) DEFAULT NULL,
  `semester` VARCHAR(50) DEFAULT NULL,
  `seats_available` INT DEFAULT 50,
  `seats_total` INT DEFAULT 50,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`program_id`) REFERENCES `programs`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_course_per_program` (`program_id`, `name`)
);

CREATE TABLE IF NOT EXISTS `student_other_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `student_course_registrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `program_id` INT,
  `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_student_course` (`user_id`, `course_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`program_id`) REFERENCES `programs`(`id`) ON DELETE SET NULL
);

-- Insert sample data if programs table is empty
INSERT IGNORE INTO `programs` (`name`, `description`) VALUES
('Computer Science', 'Study of computational systems and programming'),
('Business Administration', 'Business management and organizational studies'),
('Mass Communication', 'Media and communication studies');

-- Insert sample courses
INSERT IGNORE INTO `courses` (`program_id`, `name`, `description`) VALUES
((SELECT id FROM programs WHERE name = 'Computer Science'), 'Introduction to Programming', 'Fundamentals of programming'),
((SELECT id FROM programs WHERE name = 'Computer Science'), 'Data Structures', 'Core data structure concepts'),
((SELECT id FROM programs WHERE name = 'Computer Science'), 'Database Systems', 'Database design and management'),
((SELECT id FROM programs WHERE name = 'Computer Science'), 'Computer Networks', 'Network architecture and protocols'),
((SELECT id FROM programs WHERE name = 'Computer Science'), 'Web Development', 'Web technologies and frameworks'),
((SELECT id FROM programs WHERE name = 'Business Administration'), 'Principles of Management', 'Management fundamentals'),
((SELECT id FROM programs WHERE name = 'Business Administration'), 'Financial Accounting', 'Accounting principles'),
((SELECT id FROM programs WHERE name = 'Business Administration'), 'Marketing', 'Marketing strategies'),
((SELECT id FROM programs WHERE name = 'Business Administration'), 'Business Law', 'Legal aspects of business'),
((SELECT id FROM programs WHERE name = 'Business Administration'), 'Entrepreneurship', 'Starting and managing businesses'),
((SELECT id FROM programs WHERE name = 'Mass Communication'), 'Communication Theory', 'Communication fundamentals'),
((SELECT id FROM programs WHERE name = 'Mass Communication'), 'Journalism', 'News reporting and writing'),
((SELECT id FROM programs WHERE name = 'Mass Communication'), 'Public Relations', 'PR strategies and management'),
((SELECT id FROM programs WHERE name = 'Mass Communication'), 'Media Ethics', 'Ethical considerations in media'),
((SELECT id FROM programs WHERE name = 'Mass Communication'), 'Broadcast Production', 'Audio and video production');


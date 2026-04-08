-- Migration: Add login_count column to students table
-- Created: 2026-04-08
-- Description: Adds login_count column to track login attempts for OTP verification

ALTER TABLE `students` 
ADD COLUMN `login_count` INT(11) NOT NULL DEFAULT 0 AFTER `last_login`;

-- Reset login_count for all existing students to 0
UPDATE `students` SET `login_count` = 0 WHERE `login_count` IS NULL;

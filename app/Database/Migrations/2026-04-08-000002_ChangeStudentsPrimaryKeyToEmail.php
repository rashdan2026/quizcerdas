<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ChangeStudentsPrimaryKeyToEmail extends Migration
{
    public function up()
    {
        $db = db_connect();
        
        // Step 1: Drop existing foreign keys first
        // Drop foreign key from attendance table
        try {
            $db->query('ALTER TABLE attendance DROP FOREIGN KEY attendance_npm_foreign');
        } catch (\Exception $e) {
            // Foreign key might already be dropped, continue
        }
        
        // Drop foreign key from otp_codes table
        try {
            $db->query('ALTER TABLE otp_codes DROP FOREIGN KEY otp_codes_npm_foreign');
        } catch (\Exception $e) {
            // Foreign key might already be dropped, continue
        }
        
        // Step 2: Drop indexes on npm
        try {
            $db->query('ALTER TABLE attendance DROP INDEX meeting_id_npm');
        } catch (\Exception $e) {
            // Index might not exist, continue
        }
        
        try {
            $db->query('ALTER TABLE attendance DROP INDEX npm');
        } catch (\Exception $e) {
            // Index might not exist, continue
        }
        
        try {
            $db->query('ALTER TABLE otp_codes DROP INDEX npm');
        } catch (\Exception $e) {
            // Index might not exist, continue
        }
        
        // Step 3: Drop the existing primary key (npm) from students
        $db->query('ALTER TABLE students DROP PRIMARY KEY');
        
        // Step 4: Add email as new primary key
        $db->query('ALTER TABLE students ADD PRIMARY KEY (email)');
        
        // Step 5: Add unique constraint to npm (since it's no longer primary key)
        try {
            $db->query('ALTER TABLE students ADD UNIQUE KEY npm_unique (npm)');
        } catch (\Exception $e) {
            // Unique key might already exist, continue
        }
        
        // Step 6: Drop npm column from attendance and otp_codes (no longer needed)
        $db->query('ALTER TABLE attendance DROP COLUMN npm');
        $db->query('ALTER TABLE otp_codes DROP COLUMN npm');
        
        // Step 7: Add unique constraint on meeting_id + email
        try {
            $db->query('ALTER TABLE attendance ADD UNIQUE KEY meeting_email_unique (meeting_id, email)');
        } catch (\Exception $e) {
            // Unique constraint might already exist, continue
        }
        
        // Step 8: Add indexes on email columns
        try {
            $db->query('ALTER TABLE attendance ADD INDEX email_idx (email)');
        } catch (\Exception $e) {
            // Index might already exist, continue
        }
        
        try {
            $db->query('ALTER TABLE otp_codes ADD INDEX email_idx (email)');
        } catch (\Exception $e) {
            // Index might already exist, continue
        }
    }

    public function down()
    {
        $db = db_connect();
        
        // Reverse the changes
        // Add npm column back to otp_codes
        $db->query('ALTER TABLE otp_codes ADD COLUMN npm VARCHAR(9) NULL AFTER id');
        
        // Add npm column back to attendance
        $db->query('ALTER TABLE attendance ADD COLUMN npm VARCHAR(9) NULL AFTER meeting_id');
        
        // Drop new indexes
        try {
            $db->query('ALTER TABLE attendance DROP INDEX email_idx');
        } catch (\Exception $e) {
            // Continue
        }
        
        try {
            $db->query('ALTER TABLE otp_codes DROP INDEX email_idx');
        } catch (\Exception $e) {
            // Continue
        }
        
        try {
            $db->query('ALTER TABLE attendance DROP INDEX meeting_email_unique');
        } catch (\Exception $e) {
            // Continue
        }
        
        // Drop email primary key
        $db->query('ALTER TABLE students DROP PRIMARY KEY');
        
        // Restore npm as primary key
        $db->query('ALTER TABLE students ADD PRIMARY KEY (npm)');
        
        // Drop npm unique constraint
        try {
            $db->query('ALTER TABLE students DROP INDEX npm_unique');
        } catch (\Exception $e) {
            // Continue
        }
        
        // Restore original foreign keys
        $db->query('ALTER TABLE attendance ADD CONSTRAINT attendance_npm_foreign FOREIGN KEY (npm) REFERENCES students(npm) ON DELETE CASCADE ON UPDATE CASCADE');
        $db->query('ALTER TABLE otp_codes ADD CONSTRAINT otp_codes_npm_foreign FOREIGN KEY (npm) REFERENCES students(npm) ON DELETE CASCADE ON UPDATE CASCADE');
        
        // Restore indexes on npm
        $db->query('ALTER TABLE attendance ADD INDEX npm (npm)');
        $db->query('ALTER TABLE otp_codes ADD INDEX npm (npm)');
    }
}

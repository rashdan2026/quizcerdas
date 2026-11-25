<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuizModule extends Migration
{
    public function up()
    {
        // ======================= quiz_quizzes =======================
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'lecturer_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'subject_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'title'           => ['type' => 'VARCHAR', 'constraint' => 255],
            'description'     => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'status'          => ['type' => 'ENUM', 'constraint' => ['draft', 'scheduled', 'active', 'closed', 'archived'], 'default' => 'draft'],
            'access_code'     => ['type' => 'VARCHAR', 'constraint' => 16],
            'correct_points'  => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 5],
            'wrong_penalty'   => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1],
            'starts_at'       => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'ends_at'         => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'duration_minutes'=> ['type' => 'INT', 'constraint' => 6, 'unsigned' => true, 'default' => 60],
            'max_attempts'    => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 2],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('access_code');
        $this->forge->addKey('lecturer_id');
        $this->forge->addKey('subject_id');
        $this->forge->addForeignKey('lecturer_id', 'lecturers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('quiz_quizzes', true);

        // ================== quiz_question_batches ===================
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'quiz_id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'lecturer_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'source_type'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ai_pdf'],
            'original_filename'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_size_bytes'      => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
            'file_sha256'          => ['type' => 'CHAR', 'constraint' => 64],
            'source_title'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'requested_count'      => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'generated_count'      => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'duplicate_count'      => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'retry_count'          => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'difficulty_easy_pct'  => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 30],
            'difficulty_medium_pct'=> ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 50],
            'difficulty_hard_pct'  => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 20],
            'provider_used'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'default' => null],
            'model_used'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'status'               => ['type' => 'ENUM', 'constraint' => ['queued', 'quarantine', 'processing', 'completed', 'partial', 'failed'], 'default' => 'queued'],
            'extract_path'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'pdf_path'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'scan_status'          => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'default' => null],
            'scan_result'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'scan_at'              => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'scan_engine'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'token_prompt'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
            'token_completion'     => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
            'uploaded_at'          => ['type' => 'DATETIME', 'null' => true],
            'expires_at'           => ['type' => 'DATETIME', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('quiz_id');
        $this->forge->addKey('lecturer_id');
        $this->forge->addKey('file_sha256');
        $this->forge->addKey('expires_at');
        $this->forge->addForeignKey('quiz_id', 'quiz_quizzes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('lecturer_id', 'lecturers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('quiz_question_batches', true);

        // ====================== quiz_questions ======================
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'quiz_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'batch_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null],
            'question_text'      => ['type' => 'TEXT'],
            'option_a'           => ['type' => 'TEXT'],
            'option_b'           => ['type' => 'TEXT'],
            'option_c'           => ['type' => 'TEXT'],
            'option_d'           => ['type' => 'TEXT'],
            'option_e'           => ['type' => 'TEXT'],
            'correct_option'     => ['type' => 'ENUM', 'constraint' => ['A', 'B', 'C', 'D']],
            'explanation'        => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'difficulty_level'   => ['type' => 'ENUM', 'constraint' => ['easy', 'medium', 'hard'], 'null' => true, 'default' => null],
            'ai_difficulty_level'=> ['type' => 'ENUM', 'constraint' => ['easy', 'medium', 'hard'], 'null' => true, 'default' => null],
            'source_type'        => ['type' => 'ENUM', 'constraint' => ['manual', 'ai_pdf'], 'default' => 'manual'],
            'source_title'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'source_filename'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'source_section'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'created_by'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'sort_order'         => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'is_active'          => ['type' => 'BOOLEAN', 'default' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('quiz_id');
        $this->forge->addKey('batch_id');
        $this->forge->addForeignKey('quiz_id', 'quiz_quizzes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('batch_id', 'quiz_question_batches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('quiz_questions', true);

        // ==================== quiz_ai_providers =====================
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'provider_code'    => ['type' => 'VARCHAR', 'constraint' => 30],
            'display_name'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'base_url'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'api_key_encrypted'=> ['type' => 'TEXT', 'null' => true, 'default' => null],
            'model_name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'is_enabled'       => ['type' => 'BOOLEAN', 'default' => false],
            'priority_order'   => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true, 'default' => 1],
            'last_test_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'default' => null],
            'last_test_at'     => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('provider_code');
        $this->forge->createTable('quiz_ai_providers', true);

        // ======================= quiz_ai_logs =======================
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'batch_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null],
            'quiz_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null],
            'lecturer_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null],
            'provider_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null],
            'provider_snapshot'=> ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'default' => null],
            'model_snapshot'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'attempt_number'   => ['type' => 'INT', 'constraint' => 6, 'unsigned' => true, 'default' => 1],
            'is_fallback'      => ['type' => 'BOOLEAN', 'default' => false],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 30],
            'error_category'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'default' => null],
            'http_status'      => ['type' => 'INT', 'constraint' => 5, 'null' => true, 'default' => null],
            'latency_ms'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'requested_count'  => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'received_count'   => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'duplicate_count'  => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'token_prompt'     => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
            'token_completion' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('batch_id');
        $this->forge->addKey('quiz_id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('quiz_ai_logs', true);

        // ====================== quiz_attempts =======================
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'quiz_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'student_email'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'attempt_no'      => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1],
            'status'          => ['type' => 'ENUM', 'constraint' => ['pending', 'in_progress', 'submitted', 'timeout'], 'default' => 'pending'],
            'device_token'    => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true, 'default' => null],
            'device_bound_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'started_at'      => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'expires_at'      => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'submitted_at'    => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'raw_score'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'max_score'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'percentage'      => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true, 'default' => null],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['quiz_id', 'student_email', 'attempt_no']);
        $this->forge->addKey('quiz_id');
        $this->forge->addKey('student_email');
        $this->forge->addForeignKey('quiz_id', 'quiz_quizzes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('student_email', 'students', 'email', 'CASCADE', 'CASCADE');
        $this->forge->createTable('quiz_attempts', true);

        // ================== quiz_attempt_answers ====================
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'attempt_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'question_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'selected_option'  => ['type' => 'ENUM', 'constraint' => ['A', 'B', 'C', 'D', 'E'], 'null' => true, 'default' => null],
            'is_correct'       => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true, 'default' => null],
            'points_awarded'   => ['type' => 'INT', 'constraint' => 6, 'default' => 0],
            'started_at'       => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'answered_at'      => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'response_time_ms' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['attempt_id', 'question_id']);
        $this->forge->addKey('question_id');
        $this->forge->addForeignKey('attempt_id', 'quiz_attempts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('question_id', 'quiz_questions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('quiz_attempt_answers', true);

        // ================ quiz_attempt_question_map =================
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'attempt_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'question_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'sort_order'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'points'       => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 5],
            'penalty'      => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1],
            'snapshot'     => ['type' => 'LONGTEXT', 'null' => true],
            'option_order' => ['type' => 'VARCHAR', 'constraint' => 8, 'null' => true, 'default' => null],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['attempt_id', 'question_id']);
        $this->forge->addForeignKey('attempt_id', 'quiz_attempts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('question_id', 'quiz_questions', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('quiz_attempt_question_map', true);
    }

    public function down()
    {
        $this->forge->dropTable('quiz_attempt_question_map', true);
        $this->forge->dropTable('quiz_attempt_answers', true);
        $this->forge->dropTable('quiz_attempts', true);
        $this->forge->dropTable('quiz_ai_logs', true);
        $this->forge->dropTable('quiz_ai_providers', true);
        $this->forge->dropTable('quiz_questions', true);
        $this->forge->dropTable('quiz_question_batches', true);
        $this->forge->dropTable('quiz_quizzes', true);
    }
}

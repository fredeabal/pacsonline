<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMedicalReportsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'study_instance_uid' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'unique'     => true,
            ],
            'doctor_user_id' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'default'    => 'pending',
            ],
            'clinical_history' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'findings' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'impression' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'reported_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('status');
        $this->forge->addKey('doctor_user_id');
        $this->forge->createTable('medical_reports', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('medical_reports', true);
    }
}

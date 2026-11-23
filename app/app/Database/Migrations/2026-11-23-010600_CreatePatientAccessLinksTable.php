<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePatientAccessLinksTable extends Migration
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
            ],
            'created_by_user_id' => [
                'type' => 'INTEGER',
            ],
            'token' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'unique'     => true,
            ],
            'patient_id_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'patient_birth_date_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'patient_email' => [
                'type'       => 'VARCHAR',
                'constraint' => 190,
                'null'       => true,
            ],
            'allow_download' => [
                'type'    => 'INTEGER',
                'default' => 1,
            ],
            'views_count' => [
                'type'    => 'INTEGER',
                'default' => 0,
            ],
            'failed_attempts' => [
                'type'    => 'INTEGER',
                'default' => 0,
            ],
            'locked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_accessed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'revoked_at' => [
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
        $this->forge->addKey('study_instance_uid');
        $this->forge->createTable('patient_access_links', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('patient_access_links', true);
    }
}

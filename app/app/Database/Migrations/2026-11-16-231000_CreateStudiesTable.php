<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudiesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'orthanc_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
            ],
            'study_instance_uid' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'unique'     => true,
            ],
            'owner_user_id' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'patient_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'study_description' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'study_date' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => true,
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
        $this->forge->addKey('owner_user_id');
        $this->forge->createTable('studies', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('studies', true);
    }
}

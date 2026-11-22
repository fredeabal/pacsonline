<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudySharesTable extends Migration
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
            'recipient_label' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'pin_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'allow_download' => [
                'type'    => 'INTEGER',
                'default' => 0,
            ],
            'views_count' => [
                'type'    => 'INTEGER',
                'default' => 0,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->createTable('study_shares', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('study_shares', true);
    }
}

<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMedicalProfileToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'full_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'specialty' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'license_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'signature_file' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->dropColumn('users', ['full_name', 'specialty', 'license_number', 'signature_file']);
    }
}

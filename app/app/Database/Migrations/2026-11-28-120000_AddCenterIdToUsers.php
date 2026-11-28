<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCenterIdToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'center_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->dropColumn('users', 'center_id');
    }
}

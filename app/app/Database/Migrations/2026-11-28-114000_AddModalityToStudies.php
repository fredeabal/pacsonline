<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddModalityToStudies extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('studies', [
            'modality' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => true,
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->dropColumn('studies', 'modality');
    }
}

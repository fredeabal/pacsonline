<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddClinicalNotesToStudies extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('studies', [
            'clinical_notes' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'study_date',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        $this->forge->dropColumn('studies', 'clinical_notes');
    }
}

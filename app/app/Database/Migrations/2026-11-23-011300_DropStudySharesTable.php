<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropStudySharesTable extends Migration
{
    public function up(): void
    {
        $this->forge->dropTable('study_shares', true);
    }

    public function down(): void
    {
        // Tabla obsoleta reemplazada por patient_access_links
    }
}

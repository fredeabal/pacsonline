<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\OrthancClient;
use App\Models\StudyModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Sincroniza y persiste la modalidad DICOM real en la tabla `studies` para todos los estudios de Orthanc.
 */
class SyncModalities extends BaseCommand
{
    protected $group       = 'PACS';
    protected $name        = 'studies:sync-modalities';
    protected $description = 'Rellena la columna modality en la tabla studies para todos los estudios existentes en Orthanc.';
    protected $usage       = 'studies:sync-modalities [--batch 50]';
    protected $options     = [
        '--batch' => 'Tamaño de lote por página de Orthanc (por defecto: 50).',
    ];

    public function run(array $params): int
    {
        $batchSize  = max(10, min(200, (int) (CLI::getOption('batch') ?? 50)));
        $orthanc    = new OrthancClient();
        $studyModel = model(StudyModel::class);

        $total = $orthanc->countStudies();
        CLI::write("Iniciando sincronización de modalidades (estudios en Orthanc: {$total}, lote: {$batchSize})...", 'yellow');

        $since     = 0;
        $processed = 0;

        while (true) {
            $page = $orthanc->findStudiesPage($since, $batchSize, [], false);
            if ($page === []) {
                break;
            }

            $uids = [];
            foreach ($page as $item) {
                $uid = trim((string) ($item['MainDicomTags']['StudyInstanceUID'] ?? ''));
                if ($uid !== '') {
                    $uids[] = $uid;
                }
            }

            $recordsByUid = $studyModel->mapByStudyUids($uids);
            $studyModel->resolveAndPersistModalities($orthanc, $page, $recordsByUid);

            $count      = count($page);
            $processed += $count;
            $since     += $count;

            CLI::write("  Procesados {$processed} / {$total} estudios...", 'green');

            if ($count < $batchSize) {
                break;
            }
        }

        CLI::write("Sincronización completada ({$processed} estudios revisados).", 'light_green');

        return EXIT_SUCCESS;
    }
}

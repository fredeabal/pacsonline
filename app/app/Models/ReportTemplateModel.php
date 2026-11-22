<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo de plantillas de informes médicos por modalidad y usuario.
 */
class ReportTemplateModel extends Model
{
    protected $table          = 'report_templates';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'user_id',
        'name',
        'modality',
        'findings',
        'impression',
    ];

    /**
     * Devuelve únicamente las plantillas pertenecientes al médico indicado.
     *
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        return $this->where('user_id', $userId)
            ->orderBy('modality', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Carga el catálogo estándar de plantillas radiológicas si el médico aún no tiene ninguna.
     */
    public function seedDefaultsForUser(int $userId, bool $forceMissing = false): void
    {
        $existing = $this->forUser($userId);
        if (! $forceMissing && $existing !== []) {
            return;
        }

        $existingNames = array_column($existing, 'name');

        foreach (self::defaultCatalog() as $tpl) {
            if (in_array($tpl['name'], $existingNames, true)) {
                continue;
            }

            $this->insert([
                'user_id'    => $userId,
                'name'       => $tpl['name'],
                'modality'   => $tpl['modality'],
                'findings'   => $tpl['findings'],
                'impression' => $tpl['impression'],
            ]);
        }
    }

    /**
     * @return list<array{name: string, modality: string, findings: string, impression: string}>
     */
    public static function defaultCatalog(): array
    {
        return [
            [
                'name'       => 'Radiografía de Tórax (Normal)',
                'modality'   => 'CR',
                'findings'   => "Campos pulmonares bien aireados, sin infiltrados, consolidaciones ni nódulos.\nSenos costofrénicos libres, sin derrame pleural ni neumotórax.\nSilueta cardiomediastínica de tamaño y morfología dentro de límites normales.\nEstructuras óseas de la caja torácica sin lesiones agudas.",
                'impression' => 'Radiografía de tórax sin hallazgos patológicos agudos.',
            ],
            [
                'name'       => 'TC Craneal Simple (Normal)',
                'modality'   => 'CT',
                'findings'   => "Parénquima cerebral con adecuada diferenciación entre sustancia gris y blanca, sin áreas de isquemia aguda, hemorragia intracraneal ni lesiones ocupantes de espacio.\nSistema ventricular de tamaño, morfología y situación normales.\nCisternas basales y surcos corticales permeables.\nEstructuras de la fosa posterior y calota craneal sin alteraciones.",
                'impression' => 'TC craneal sin evidencia de patología intracraneal aguda.',
            ],
            [
                'name'       => 'TC de Tórax (Normal)',
                'modality'   => 'CT',
                'findings'   => "Parénquima pulmonar sin consolidaciones, nódulos sospechosos ni alteraciones intersticiales.\nTráquea y bronquios principales permeables.\nMediastino sin adenopatías de tamaño significativo. Grandes vasos de calibre conservado.\nAusencia de derrame pleural o pericárdico.",
                'impression' => 'TC torácica dentro de límites normales.',
            ],
            [
                'name'       => 'TC Abdomen y Pelvis (Normal)',
                'modality'   => 'CT',
                'findings'   => "Hígado de tamaño y atenuación homogénea, sin lesiones focales. Vía biliar intra y extrahepática no dilatada.\nPáncreas, bazo y glándulas suprarrenales de morfología normal.\nAmbos riñones de tamaño y captación simétrica, sin hidronefrosis ni litiasis.\nAsas intestinales de calibre normal, sin líquido libre intraperitoneal ni adenopatías.",
                'impression' => 'TC abdominopélvica sin hallazgos patológicos significativos.',
            ],
            [
                'name'       => 'RM Cerebral (Normal)',
                'modality'   => 'MR',
                'findings'   => "Parénquima supra e infratentorial de intensidad de señal conservada en todas las secuencias, sin restricciones en difusión ni lesiones focales.\nSistema ventricular simétrico y de calibre normal. Línea media centrada.\nEstructuras vasculares del polígono de Willis con vacío de señal conservado.",
                'impression' => 'Resonancia magnética cerebral dentro de la normalidad.',
            ],
            [
                'name'       => 'RM Columna Lumbar (Normal)',
                'modality'   => 'MR',
                'findings'   => "Alineación vertebral conservada con altura y señal de cuerpos vertebrales normales.\nDiscos intervertebrales de altura e hidratación conservadas, sin protrusiones ni hernias que comprometan el canal raquídeo ni los agujeros de conjunción.\nCono medular y raíces de la cola de caballo de aspecto normal.",
                'impression' => 'RM de columna lumbar sin discopatía compresiva ni alteraciones significativas.',
            ],
            [
                'name'       => 'Ecografía Abdominal (Normal)',
                'modality'   => 'US',
                'findings'   => "Hígado de tamaño normal y ecoestructura homogénea, sin lesiones focales.\nVesícula biliar distendida de paredes finas, sin litiasis en su interior. Vía biliar de calibre normal.\nPáncreas y bazo sin alteraciones ecográficas.\nAmbos riñones de tamaño, morfología y relación corticomedular conservados, sin ectasia pielocalicial.\nAusencia de líquido libre intraabdominal.",
                'impression' => 'Ecografía abdominal sin hallazgos patológicos.',
            ],
            [
                'name'       => 'Ecografía Tiroidea (Normal)',
                'modality'   => 'US',
                'findings'   => "Glándula tiroides de tamaño, morfología y ecoestructura homogénea en ambos lóbulos e istmo.\nNo se observan nódulos sólidos ni quísticos.\nCadenas ganglionares cervicales sin adenopatías de aspecto patológico.",
                'impression' => 'Ecografía tiroidea dentro de límites normales.',
            ],
        ];
    }
}

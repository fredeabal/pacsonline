<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ReportTemplateModel;
use CodeIgniter\HTTP\RedirectResponse;

/////////////////////////////////////////////////////////////////////////////////
// Gestión de plantillas de informes médicos por usuario y modalidad DICOM.    //
/////////////////////////////////////////////////////////////////////////////////
class TemplateController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Lista las plantillas de informe del médico autenticado.                 //
    /////////////////////////////////////////////////////////////////////////////
    public function index(): string|RedirectResponse
    {
        if (! auth()->user()?->can('reports.review')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $model = model(ReportTemplateModel::class);
        $model->seedDefaultsForUser((int) auth()->id());
        $templates = $model->forUser((int) auth()->id());

        return view('templates/index', [
            'title'     => 'Mis Plantillas | PACS Online',
            'templates' => $templates,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Muestra el formulario para crear una nueva plantilla de informe.        //
    /////////////////////////////////////////////////////////////////////////////
    public function create(): string|RedirectResponse
    {
        if (! auth()->user()?->can('reports.review')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        return view('templates/create', [
            'title' => 'Nueva Plantilla | PACS Online',
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Guarda una nueva plantilla de informe en la base de datos.              //
    /////////////////////////////////////////////////////////////////////////////
    public function store(): RedirectResponse
    {
        if (! auth()->user()?->can('reports.review')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $rules = [
            'name' => [
                'label' => 'Nombre',
                'rules' => 'required|min_length[2]|max_length[150]',
            ],
            'modality' => [
                'label' => 'Modalidad',
                'rules' => 'required|max_length[32]',
            ],
            'findings' => [
                'label' => 'Hallazgos',
                'rules' => 'required|min_length[5]|max_length[20000]',
            ],
            'impression' => [
                'label' => 'Conclusión',
                'rules' => 'required|min_length[3]|max_length[10000]',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = $this->validator->getValidated();
        model(ReportTemplateModel::class)->insert([
            'user_id'    => (int) auth()->id(),
            'name'       => $data['name'],
            'modality'   => $data['modality'],
            'findings'   => $data['findings'],
            'impression' => $data['impression'],
        ]);

        return redirect()->to('/templates')->with('message', 'Plantilla creada.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Muestra el formulario de edición de una plantilla existente.            //
    /////////////////////////////////////////////////////////////////////////////
    public function edit(int $id): string|RedirectResponse
    {
        if (! auth()->user()?->can('reports.review')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $template = model(ReportTemplateModel::class)->find($id);
        if ($template === null || (int) $template['user_id'] !== (int) auth()->id()) {
            return redirect()->to('/templates')->with('error', 'Plantilla no encontrada.');
        }

        return view('templates/edit', [
            'title'    => 'Editar Plantilla | PACS Online',
            'template' => $template,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Actualiza los datos de una plantilla de informe existente.              //
    /////////////////////////////////////////////////////////////////////////////
    public function update(int $id): RedirectResponse
    {
        if (! auth()->user()?->can('reports.review')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $model    = model(ReportTemplateModel::class);
        $template = $model->find($id);
        if ($template === null || (int) $template['user_id'] !== (int) auth()->id()) {
            return redirect()->to('/templates')->with('error', 'Sin permiso.');
        }

        $rules = [
            'name' => [
                'label' => 'Nombre',
                'rules' => 'required|min_length[2]|max_length[150]',
            ],
            'modality' => [
                'label' => 'Modalidad',
                'rules' => 'required|max_length[32]',
            ],
            'findings' => [
                'label' => 'Hallazgos',
                'rules' => 'required|min_length[5]|max_length[20000]',
            ],
            'impression' => [
                'label' => 'Conclusión',
                'rules' => 'required|min_length[3]|max_length[10000]',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = $this->validator->getValidated();
        $model->update($id, $data);

        return redirect()->to('/templates')->with('message', 'Plantilla actualizada.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Elimina una plantilla de informe del usuario autenticado.               //
    /////////////////////////////////////////////////////////////////////////////
    public function delete(int $id): RedirectResponse
    {
        if (! auth()->user()?->can('reports.review')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $model    = model(ReportTemplateModel::class);
        $template = $model->find($id);
        if ($template === null || (int) $template['user_id'] !== (int) auth()->id()) {
            return redirect()->to('/templates')->with('error', 'Sin permiso.');
        }

        $model->delete($id);

        return redirect()->to('/templates')->with('message', 'Plantilla eliminada.');
    }
}

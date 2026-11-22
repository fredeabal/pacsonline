# Guía de Diseño — PACS Online (Modernize + Estilo `deca`)

Documento de referencia visual y estructural basado en la plantilla **Modernize (Bootstrap 5)** y las convenciones de la aplicación `deca` (`Blue_Theme`).

---

## 1. Principios de Estructura y Navegación
- **Páginas separadas (NUNCA todo junto en la misma vista):**
  - Cada módulo sigue el patrón estricto **`index.php` (Listado)** → **`create.php` (Crear)** → **`edit.php` (Editar)**.
  - Los listados contienen únicamente el buscador, la tabla (con acciones) y un botón superior derecho para ir a la página de creación.
  - Cuando una vista tenga varios bloques (ej. datos del estudio + editor de informe), **se apilan verticalmente uno sobre otro** (ancho completo), nunca en dos cards lado a lado.
- **Layout:** Vertical con sidebar fijo (`data-layout="vertical"`, `data-sidebartype="full"`, `data-color-theme="Blue_Theme"`).
- **Tema claro / oscuro:** Soporte dual (`data-bs-theme="light"` / `"dark"`) persistido en `localStorage` con sincronización automática de iconos sol/luna.

---

## 2. Tipografía y Paleta de Colores (`styles.css` + `custom.css` de `deca`)
- **Fuente:** `Plus Jakarta Sans` (configurada en `styles.css` de `deca`).
- **Tarjetas:** `border-radius: 12px !important;`.
- **Paleta:**

| Token | Valor | Uso |
| :--- | :--- | :--- |
| `--bs-primary` | `#5d87ff` (`rgb: 93, 135, 255`) | Botones primarios, cabeceras `bg-primary-subtle` (`text-primary`), enlaces y foco |
| `--bs-secondary` | `#49beff` | Acentos secundarios |
| `--bs-success` | `#34c759` | Estado `Informado`, enlace `Activo`, icono check circular |
| `--bs-warning` | `#FAAD14` | Estado `En revisión`, enlaces expirados |
| `--bs-danger` | `#b31b34` | Estado `Revocado`, eliminar, errores |
| `--bs-info` | `#539bff` | Estado `Pendiente` |
| `--bs-dark` | `#5a6a85` | Botones secundarios / cancelar |

---

## 3. Patrón Visual de Listados y Tablas
1. **Cabecera de Card:**
   ```html
   <div class="card">
     <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
       <h5 class="mb-0 text-primary fw-semibold">Título del Listado</h5>
       <a href="..." class="btn btn-sm btn-primary">
         <i class="ti ti-plus fs-4"></i> <span class="d-none d-md-inline ms-1">Crear</span>
       </a>
     </div>
     <div class="card-body"> ... </div>
   </div>
   ```
2. **Buscador en cada tabla:**
   ```html
   <div class="input-group mb-3">
     <span class="input-group-text"><i class="ti ti-search"></i></span>
     <input type="text" class="form-control table-search-input" data-target="#miTabla" placeholder="Buscar...">
   </div>
   ```
3. **Contenedor de Tabla y Filas Uniformes (Mobile-First):**
   - **Escritorio (`d-none d-md-block`):** `<div class="mb-4 border rounded-1 table-responsive">` con `<table class="table text-nowrap mb-0 align-middle table-hover">`.
   - **Columna `Fecha` siempre primero y filas clicables:** Todas las tablas comienzan por la columna `Fecha` (formato `dd/mm/aaaa`). Las filas de listados llevan `class="cursor-pointer"` y `onclick` para navegar directamente a la ficha o edición del registro (aislando la celda/contenedor de botones con `onclick="event.stopPropagation();"`).
   - **Columna `Acciones` exclusivamente con menú de 3 puntos (`ti-dots-vertical fs-6`):** Igual que en `deca`, ninguna tabla ni tarjeta móvil lleva botones sueltos en la fila. Se usa únicamente `<div class="dropdown dropstart d-inline-block">` con `<a ... data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}'><i class="ti ti-dots-vertical fs-6"></i></a>` y opciones en `dropdown-menu dropdown-menu-end shadow-sm`.
   - **Filas siempre del mismo tamaño (`52px`):** Todas las filas de `tbody` tienen altura fija de `52px`, alineación vertical media (`vertical-align: middle`) y una sola línea por celda (`white-space: nowrap`). Está prohibido apilar dos líneas de texto (`d-block` + subtítulo debajo) dentro de una celda de tabla en escritorio; si hay dos datos (ej. nombre y correo, destinatario y caducidad), se muestran en columnas independientes. Los `Select2` dentro de celdas usan altura compacta de `31px`.
   - **Móvil (`d-block d-md-none`):** Tarjetas individuales `<div class="card border shadow-sm mb-3 mobile-card-item">` con badges y botones compactos `btn-sm`, filtradas simultáneamente por `.table-search-input[data-cards-target]`.
4. **Badges Siempre Transparentes e Iconos de Estado:**
   - **Badges siempre transparentes:** Todos los badges de la aplicación usan fondo translúcido (`rgba(var(--bs-*-rgb), 0.12)`), borde sutil (`1px solid rgba(var(--bs-*-rgb), 0.45)`), texto en su color semántico y fuente pequeña (`fs-2`), nunca rellenos sólidos opacos:
     - `badge bg-success-subtle text-success border border-success-subtle fs-2`
     - `badge bg-warning-subtle text-warning border border-warning-subtle fs-2`
     - `badge bg-info-subtle text-info border border-info-subtle fs-2`
     - `badge bg-danger-subtle text-danger border border-danger-subtle fs-2`
     - `badge bg-primary-subtle text-primary border border-primary-subtle fs-2`
     - `badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2`
   - Estados booleanos activos/inactivos con icono circular de `28px × 28px` (`rounded-circle bg-success-subtle text-success`).
5. **Formato de Fechas y Visor OHIF:**
   - **Fechas siempre `dd/mm/aaaa`:** Toda fecha mostrada en la interfaz, PDFs y correos debe pasar por `format_date()` (`dd/mm/aaaa`) o `format_datetime()` (`dd/mm/aaaa HH:mm`).
   - **Visor OHIF siempre en español:** Todos los enlaces a OHIF se generan con `ohif_url($uid)` (`&lng=es`) y `localStorage.i18nextLng = 'es'`.
6. **Subida de Archivos (`dropzone-modern-wrapper` de `deca`):**
   - Usar siempre el contenedor `dropzone-modern-wrapper p-5 rounded-3 border border-2 border-dashed border-secondary-subtle text-center bg-body-tertiary` con `<input type="file" class="visually-hidden">`, icono circular `bg-primary-subtle text-primary`, botón `btn-outline-primary` y vista previa del archivo seleccionado (soporte drag & drop).
7. **Botones de Formularios y Selectores:**
   - Todos los botones de acción al pie de los formularios van siempre en tamaño pequeño (`btn-sm`) y centrados (`d-flex justify-content-center gap-2`), y cualquier botón auxiliar junto a un campo va también en `btn-sm`.
   - Todos los `<select class="form-select">` llevan **Select2** con buscador habilitado (`minimumResultsForSearch: 0`) para poder escribir y filtrar opciones al instante.
8. **Pantallas de Acceso y Recuperación (`Auth/`):**
   - Layout dividido `min-vh-100`: columna izquierda azul (`.bg-primary-gradient` + `particles-js`) y columna derecha con el formulario centrado (`auth-max-width`).
   - El icono del ojo para ver/ocultar contraseña va **dentro del input** (`position-relative` + `position-absolute top-50 end-0 translate-middle-y`) con fondo transparente y color gris (`border-0 bg-transparent text-muted`), nunca en azul ni como botón externo.
   - Menús contextuales de acciones con `ti-dots-vertical` (`dropdown dropstart`) o botones compactos `btn-sm`.

---

## 4. Mapa de Páginas Separadas
- **Usuarios (`/users`):**
  - `users/index.php` (Listado + buscador + botón "Crear usuario")
  - `users/create.php` (Formulario dedicado)
- **Estudios (`/studies`):**
  - `studies/index.php` (Listado + buscador + asignación + acciones)
  - `studies/upload.php` (Subida dedicada `.dcm` / `.zip`)
- **Compartición (`/studies/share/{uid}`):**
  - `shares/manage.php` (Listado de enlaces + buscador + botón "Nuevo enlace")
  - `shares/create.php` (Formulario dedicado para crear enlace + PIN + caducidad)
  - `shares/pin.php` / `shares/view.php` / `shares/expired.php` (Acceso de médico invitado)
- **Informes Médicos (`/reports`):**
  - `reports/index.php` (Worklist con filtros + buscador)
  - `reports/edit.php` (Editor clínico + plantillas + dictado por voz)
  - `reports/pdf.php` (Plantilla PDF)
- **Auditoría (`/audit`):**
  - `audit/index.php` (Listado + buscador)

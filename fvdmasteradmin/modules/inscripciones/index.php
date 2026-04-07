<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_init.php';
fvd_module_require_roles();

require_once __DIR__ . '/Controller.php';
$ctrl = new InscripcionesController();

$fvd_page_title = 'Inscripciones';
require FVD_MASTER_ROOT . '/includes/layout_header.php';
include __DIR__ . '/list.view.php';
require FVD_MASTER_ROOT . '/includes/layout_footer.php';

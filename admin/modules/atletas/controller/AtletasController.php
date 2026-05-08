<?php

declare(strict_types=1);

final class AtletasController
{
    private FvdAdminService $svc;
    private AtletasModuleService $module;
    private string $selfUrl;

    public function __construct(FvdAdminService $svc, AtletasModuleService $module, string $selfUrl)
    {
        $this->svc = $svc;
        $this->module = $module;
        $this->selfUrl = $selfUrl;
    }

    public function handleMutations(string &$fvd_error): void
    {
        if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
            $this->svc->atletasDelete((int) $_GET['id']);
            header('Location: ' . fvd_return_preserve_query_params($this->selfUrl . '?action=list'));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'toggle_activo' && AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            $tid = (int) ($_POST['id'] ?? 0);
            if ($tid > 0) {
                $this->svc->atletasToggleActivo($tid);
            }
            header('Location: ' . fvd_return_preserve_query_params($this->selfUrl . '?action=list'));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'dar_baja') {
            $tid = (int) ($_POST['id'] ?? 0);
            if ($tid > 0) {
                $this->svc->atletasDarBaja($tid);
            }
            header('Location: ' . fvd_return_preserve_query_params($this->selfUrl . '?action=list'));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'restaurar_atleta') {
            $tid = (int) ($_POST['id'] ?? 0);
            if ($tid > 0) {
                $this->svc->atletasRestaurarDesdeBaja($tid);
            }
            header('Location: ' . fvd_return_preserve_query_params($this->selfUrl . '?action=list'));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save') {
            try {
                $sid = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
                $this->svc->atletasSave($sid, $_POST, $_FILES);
                $redir = $this->selfUrl . '?action=list';
                if (AuthService::isDelegadoAsociacion()) {
                    $mineAsoc = (int) (AuthService::idAsociacion() ?? 0);
                    $redir = $this->selfUrl . '?action=list&alcance=asociacion&asociacion_id=' . $mineAsoc;
                }
                header('Location: ' . $redir);
                exit;
            } catch (Throwable $e) {
                $fvd_error = $e->getMessage();
                error_log('[admin/atletas] ' . $fvd_error);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'traspaso_confirm') {
            require_once FVD_PROJECT_ROOT . '/src/Services/TraspasoService.php';
            AuthService::ensureSession();
            if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
                http_response_code(403);
                header('Content-Type: text/plain; charset=UTF-8');
                echo 'Solo personal FVD puede confirmar traspasos.';
                exit;
            }
            $taid = isset($_POST['atleta_id']) ? (int) $_POST['atleta_id'] : 0;
            $tdest = isset($_POST['asociacion_destino_id']) ? (int) $_POST['asociacion_destino_id'] : 0;
            try {
                \FvdPortal\Services\TraspasoService::ejecutar(fvd_db(), $taid, $tdest, AuthService::userId());
                header('Location: ' . fvd_return_preserve_query_params($this->selfUrl . '?action=list'));
                exit;
            } catch (Throwable $e) {
                $_SESSION['fvd_traspaso_error'] = $e->getMessage();
                header('Location: ' . fvd_return_preserve_query_params($this->selfUrl . '?action=traspaso&id=' . $taid));
                exit;
            }
        }
    }
}

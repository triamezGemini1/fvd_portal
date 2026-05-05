<?php

declare(strict_types=1);

/**
 * Notificaciones Telegram (omnicanal) tras despachar invitaciones masivas.
 * Requiere TELEGRAM_BOT_TOKEN en .env y columna delegados.telegram_chat_id.
 */

if (!function_exists('fvd_notifier_ensure_schema')) {
    /**
     * Columnas necesarias: delegados.telegram_chat_id, torneosact.invitaciones_despachadas.
     */
    function fvd_notifier_ensure_schema(PDO $pdo): void
    {
        try {
            $pdo->exec(
                'ALTER TABLE delegados ADD COLUMN telegram_chat_id VARCHAR(64) NULL DEFAULT NULL COMMENT \'Chat id Telegram vinculado\''
            );
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false) {
                error_log('[fvd_notifier] delegados.telegram_chat_id: ' . $e->getMessage());
            }
        }
        try {
            $pdo->exec(
                'ALTER TABLE torneosact ADD COLUMN invitaciones_despachadas TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'1 tras despachar invitaciones\''
            );
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false) {
                error_log('[fvd_notifier] torneosact.invitaciones_despachadas: ' . $e->getMessage());
            }
        }
        try {
            $pdo->exec(
                'ALTER TABLE torneosact ADD COLUMN fecha_limite_cambios date DEFAULT NULL COMMENT \'Tras esta fecha: nómina solo consulta\''
            );
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false) {
                error_log('[fvd_notifier] torneosact.fecha_limite_cambios: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('fvd_notifier_inscripcion_url_absoluta')) {
    function fvd_notifier_inscripcion_url_absoluta(PDO $pdo, int $torneoId): string
    {
        if ($torneoId <= 0) {
            return '';
        }
        $projRoot = dirname(__DIR__);
        if (!function_exists('full_url')) {
            require_once $projRoot . '/config/paths.php';
        }
        $st = $pdo->prepare('SELECT torneo, COALESCE(grupo_evento_id, 0) AS gid FROM torneosact WHERE torneo = :t LIMIT 1');
        $st->execute([':t' => $torneoId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return '';
        }
        $gid = (int) ($row['gid'] ?? 0);
        $q = ['torneo_id' => $torneoId, 'embedded' => '1'];
        if ($gid > 0) {
            $q['campeonato_id'] = $gid;
        }

        return full_url('modules/torneo_inscripcion/index.php?' . http_build_query($q));
    }
}

if (!function_exists('fvd_notifier_telegram_send')) {
    /**
     * @param array<string, mixed>|null $replyMarkup Array para reply_markup (p. ej. inline_keyboard)
     */
    function fvd_notifier_telegram_send(string $chatId, string $text, ?array $replyMarkup = null, ?string $parseMode = null): bool
    {
        $chatId = trim($chatId);
        if ($chatId === '' || $text === '') {
            return false;
        }
        if (!function_exists('env')) {
            require_once dirname(__DIR__) . '/config/env.php';
            Env::load();
        }
        $token = trim((string) (function_exists('env') ? env('TELEGRAM_BOT_TOKEN', '') : ''));
        if ($token === '') {
            error_log('[fvd_notifier] TELEGRAM_BOT_TOKEN no configurado.');

            return false;
        }
        $url = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage';
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => '0',
        ];
        if ($parseMode !== null && $parseMode !== '') {
            $params['parse_mode'] = $parseMode;
        }
        if ($replyMarkup !== null && $replyMarkup !== []) {
            $params['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }
        $payload = http_build_query($params);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 12,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            error_log('[fvd_notifier] sendMessage falló para chat ' . $chatId);

            return false;
        }
        $j = json_decode($raw, true);

        return is_array($j) && !empty($j['ok']);
    }
}

if (!function_exists('fvd_notifier_panel_delegado_url_absoluta')) {
    function fvd_notifier_panel_delegado_url_absoluta(string $accessToken): string
    {
        $t = trim($accessToken);
        if ($t === '') {
            return '';
        }
        $projRoot = dirname(__DIR__);
        if (!function_exists('full_url')) {
            require_once $projRoot . '/config/paths.php';
        }
        $q = [
            'token' => $t,
            'embedded' => '1',
            'fvd_master_embed' => '1',
        ];

        return full_url('fvdmasteradmin/delegado_entrar_torneo.php?' . http_build_query($q));
    }
}

if (!function_exists('fvd_notifier_convocatoria_nacional_telegram')) {
    /**
     * Mensaje HTML + botón URL al panel del delegado (token).
     *
     * @return int Mensajes enviados (intentos OK)
     */
    function fvd_notifier_convocatoria_nacional_telegram(PDO $pdo, int $torneoId): int
    {
        if ($torneoId <= 0) {
            return 0;
        }
        fvd_notifier_ensure_schema($pdo);
        $stT = $pdo->prepare('SELECT nombre, lugar, fechator, fecha_limite_cambios FROM torneosact WHERE torneo = :t LIMIT 1');
        $stT->execute([':t' => $torneoId]);
        $tr = $stT->fetch(PDO::FETCH_ASSOC);
        if ($tr === false) {
            return 0;
        }
        $nombre = htmlspecialchars(trim((string) ($tr['nombre'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: 'Torneo';
        $sede = htmlspecialchars(trim((string) ($tr['lugar'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: '—';
        $limInsc = '—';
        if (!empty($tr['fechator'])) {
            try {
                $limInsc = (new \DateTimeImmutable((string) $tr['fechator']))->format('d/m/Y');
            } catch (Throwable $e) {
                $limInsc = htmlspecialchars(substr((string) $tr['fechator'], 0, 10), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        $limCambios = '';
        if (!empty($tr['fecha_limite_cambios'])) {
            try {
                $limCambios = (new \DateTimeImmutable((string) $tr['fecha_limite_cambios']))->format('d/m/Y');
            } catch (Throwable $e) {
                $limCambios = substr((string) $tr['fecha_limite_cambios'], 0, 10);
            }
        }

        $sql = 'SELECT DISTINCT d.id, TRIM(d.telegram_chat_id) AS telegram_chat_id, n.access_token
                FROM delegados d
                INNER JOIN fvd_delegado_notif_torneo n ON n.delegado_id = d.id AND n.torneo_id = :t
                WHERE d.activo = 1 AND d.telegram_chat_id IS NOT NULL AND TRIM(d.telegram_chat_id) <> \'\'
                  AND n.access_token IS NOT NULL AND TRIM(n.access_token) <> \'\'';
        $st = $pdo->prepare($sql);
        $st->execute([':t' => $torneoId]);
        $nOk = 0;
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $cid = (string) ($row['telegram_chat_id'] ?? '');
            $tok = trim((string) ($row['access_token'] ?? ''));
            if ($cid === '' || $tok === '') {
                continue;
            }
            $panelUrl = fvd_notifier_panel_delegado_url_absoluta($tok);
            if ($panelUrl === '') {
                continue;
            }
            $html = '<b>🏆 CONVOCATORIA NACIONAL 🏆</b>' . "\n"
                . '<b>Torneo:</b> ' . $nombre . "\n"
                . '<b>Sede:</b> ' . $sede . "\n"
                . '<b>Límite de inscripción (fecha evento):</b> ' . $limInsc . "\n";
            if ($limCambios !== '') {
                $html .= '<b>Límite de cambios nómina:</b> ' . htmlspecialchars($limCambios, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "\n";
            }
            $markup = [
                'inline_keyboard' => [
                    [['text' => 'Abrir Panel de Gestión', 'url' => $panelUrl]],
                ],
            ];
            if (fvd_notifier_telegram_send($cid, $html, $markup, 'HTML')) {
                ++$nOk;
            }
        }

        return $nOk;
    }
}

if (!function_exists('fvd_notifier_convocatoria_pendientes_telegram')) {
    /**
     * Igual que la convocatoria nacional, pero solo delegados indicados (alta posterior al primer despacho).
     *
     * @param list<int> $delegadoIds
     *
     * @return int Mensajes enviados (intentos OK)
     */
    function fvd_notifier_convocatoria_pendientes_telegram(PDO $pdo, int $torneoId, array $delegadoIds): int
    {
        $delegadoIds = array_values(array_unique(array_filter(array_map(static function ($v): int {
            return (int) $v;
        }, $delegadoIds), static function (int $v): bool {
            return $v > 0;
        })));
        if ($torneoId <= 0 || $delegadoIds === []) {
            return 0;
        }
        fvd_notifier_ensure_schema($pdo);
        $stT = $pdo->prepare('SELECT nombre, lugar, fechator, fecha_limite_cambios FROM torneosact WHERE torneo = :t LIMIT 1');
        $stT->execute([':t' => $torneoId]);
        $tr = $stT->fetch(PDO::FETCH_ASSOC);
        if ($tr === false) {
            return 0;
        }
        $nombre = htmlspecialchars(trim((string) ($tr['nombre'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: 'Torneo';
        $sede = htmlspecialchars(trim((string) ($tr['lugar'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: '—';
        $limInsc = '—';
        if (!empty($tr['fechator'])) {
            try {
                $limInsc = (new \DateTimeImmutable((string) $tr['fechator']))->format('d/m/Y');
            } catch (Throwable $e) {
                $limInsc = htmlspecialchars(substr((string) $tr['fechator'], 0, 10), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        $limCambios = '';
        if (!empty($tr['fecha_limite_cambios'])) {
            try {
                $limCambios = (new \DateTimeImmutable((string) $tr['fecha_limite_cambios']))->format('d/m/Y');
            } catch (Throwable $e) {
                $limCambios = substr((string) $tr['fecha_limite_cambios'], 0, 10);
            }
        }
        $inParts = [];
        $params = [':t' => $torneoId];
        foreach ($delegadoIds as $k => $did) {
            $key = ':d' . $k;
            $inParts[] = $key;
            $params[$key] = $did;
        }
        $sql = 'SELECT DISTINCT d.id, TRIM(d.telegram_chat_id) AS telegram_chat_id, n.access_token
                FROM delegados d
                INNER JOIN fvd_delegado_notif_torneo n ON n.delegado_id = d.id AND n.torneo_id = :t
                WHERE d.activo = 1 AND d.telegram_chat_id IS NOT NULL AND TRIM(d.telegram_chat_id) <> \'\'
                  AND n.access_token IS NOT NULL AND TRIM(n.access_token) <> \'\'
                  AND d.id IN (' . implode(',', $inParts) . ')';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $nOk = 0;
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $cid = (string) ($row['telegram_chat_id'] ?? '');
            $tok = trim((string) ($row['access_token'] ?? ''));
            if ($cid === '' || $tok === '') {
                continue;
            }
            $panelUrl = fvd_notifier_panel_delegado_url_absoluta($tok);
            if ($panelUrl === '') {
                continue;
            }
            $html = '<b>🏆 Acceso al torneo (pendiente)</b>' . "\n"
                . '<b>Torneo:</b> ' . $nombre . "\n"
                . '<b>Sede:</b> ' . $sede . "\n"
                . '<b>Límite de inscripción (fecha evento):</b> ' . $limInsc . "\n";
            if ($limCambios !== '') {
                $html .= '<b>Límite de cambios nómina:</b> ' . htmlspecialchars($limCambios, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "\n";
            }
            $markup = [
                'inline_keyboard' => [
                    [['text' => 'Abrir Panel de Gestión', 'url' => $panelUrl]],
                ],
            ];
            if (fvd_notifier_telegram_send($cid, $html, $markup, 'HTML')) {
                ++$nOk;
            }
        }

        return $nOk;
    }
}

if (!function_exists('fvd_notifier_despachar_telegram_para_torneo')) {
    /**
     * Un mensaje por delegado con telegram_chat_id (solo activos con fila de notificación al torneo).
     */
    function fvd_notifier_despachar_telegram_para_torneo(PDO $pdo, int $torneoId): void
    {
        if ($torneoId <= 0) {
            return;
        }
        fvd_notifier_ensure_schema($pdo);
        $stT = $pdo->prepare('SELECT nombre, fechator FROM torneosact WHERE torneo = :t LIMIT 1');
        $stT->execute([':t' => $torneoId]);
        $tr = $stT->fetch(PDO::FETCH_ASSOC);
        if ($tr === false) {
            return;
        }
        $nombre = trim((string) ($tr['nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = 'Torneo #' . $torneoId;
        }
        $fechaStr = '—';
        if (!empty($tr['fechator'])) {
            try {
                $fechaStr = (new DateTimeImmutable((string) $tr['fechator']))->format('d/m/Y');
            } catch (Throwable $e) {
                $fechaStr = (string) $tr['fechator'];
            }
        }
        $urlInsc = fvd_notifier_inscripcion_url_absoluta($pdo, $torneoId);
        if ($urlInsc === '') {
            return;
        }
        $msg = '🏆 Nuevo Torneo: ' . $nombre . ' | Fecha: ' . $fechaStr
            . ' | Haz clic aquí para inscribir a tu asociación: ' . $urlInsc;

        $sql = 'SELECT DISTINCT d.id, TRIM(d.telegram_chat_id) AS telegram_chat_id
                FROM delegados d
                INNER JOIN fvd_delegado_notif_torneo n ON n.delegado_id = d.id AND n.torneo_id = :t
                WHERE d.activo = 1 AND d.telegram_chat_id IS NOT NULL AND TRIM(d.telegram_chat_id) <> \'\'';
        $st = $pdo->prepare($sql);
        $st->execute([':t' => $torneoId]);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $cid = (string) ($row['telegram_chat_id'] ?? '');
            if ($cid === '') {
                continue;
            }
            fvd_notifier_telegram_send($cid, $msg);
        }
    }
}

if (!function_exists('fvd_notifier_despachar_telegram_tras_grupo')) {
    /**
     * Tras vincular grupo: un Telegram por delegado (enlace al torneo representativo = menor ID).
     *
     * @param list<int> $torneoIds
     */
    function fvd_notifier_despachar_telegram_tras_grupo(PDO $pdo, array $torneoIds): void
    {
        $ids = [];
        foreach ($torneoIds as $x) {
            $n = (int) $x;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }
        $ids = array_values($ids);
        if ($ids === []) {
            return;
        }
        sort($ids, SORT_NUMERIC);
        $rep = $ids[0];
        fvd_notifier_ensure_schema($pdo);

        $stT = $pdo->prepare('SELECT nombre, fechator, COALESCE(grupo_evento_id, 0) AS gid FROM torneosact WHERE torneo = :t LIMIT 1');
        $stT->execute([':t' => $rep]);
        $tr = $stT->fetch(PDO::FETCH_ASSOC);
        if ($tr === false) {
            return;
        }
        $gid = (int) ($tr['gid'] ?? 0);
        $nombreNom = trim((string) ($tr['nombre'] ?? ''));
        if ($gid > 0) {
            try {
                $stNom = $pdo->prepare('SELECT nombre_nominal FROM fvd_campeonato_grupo WHERE grupo_evento_id = :g LIMIT 1');
                $stNom->execute([':g' => $gid]);
                $nn = trim((string) ($stNom->fetchColumn() ?: ''));
                if ($nn !== '') {
                    $nombreNom = $nn;
                }
            } catch (Throwable $e) {
            }
        }
        if ($nombreNom === '') {
            $nombreNom = 'Campeonato #' . $rep;
        }
        $fechaStr = '—';
        if (!empty($tr['fechator'])) {
            try {
                $fechaStr = (new DateTimeImmutable((string) $tr['fechator']))->format('d/m/Y');
            } catch (Throwable $e) {
                $fechaStr = (string) $tr['fechator'];
            }
        }
        $urlInsc = fvd_notifier_inscripcion_url_absoluta($pdo, $rep);
        if ($urlInsc === '') {
            return;
        }
        $msg = '🏆 Nuevo Torneo: ' . $nombreNom . ' | Fecha: ' . $fechaStr
            . ' | Haz clic aquí para inscribir a tu asociación: ' . $urlInsc;

        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT DISTINCT d.id, TRIM(d.telegram_chat_id) AS telegram_chat_id
                FROM delegados d
                INNER JOIN fvd_delegado_notif_torneo n ON n.delegado_id = d.id AND n.torneo_id IN ($ph)
                WHERE d.activo = 1 AND d.telegram_chat_id IS NOT NULL AND TRIM(d.telegram_chat_id) <> ''";
        $st = $pdo->prepare($sql);
        $st->execute($ids);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $cid = (string) ($row['telegram_chat_id'] ?? '');
            if ($cid === '') {
                continue;
            }
            fvd_notifier_telegram_send($cid, $msg);
        }
    }
}

if (!function_exists('fvd_invitaciones_monitor_line')) {
    /**
     * Texto compacto para el panel maestro (WorkspaceHome).
     */
    function fvd_invitaciones_monitor_line(PDO $pdo): string
    {
        try {
            fvd_notifier_ensure_schema($pdo);
            $hasEsCampeonato = false;
            try {
                $pdo->query('SELECT es_campeonato FROM torneosact LIMIT 0');
                $hasEsCampeonato = true;
            } catch (Throwable $e) {
                $hasEsCampeonato = false;
            }
            $condCamp = $hasEsCampeonato ? 'COALESCE(t.es_campeonato, 0) = 1' : '(t.tipo = 2)';
            $totCam = (int) $pdo->query('SELECT COUNT(*) FROM torneosact t WHERE ' . $condCamp)->fetchColumn();
            $desp = (int) $pdo->query(
                'SELECT COUNT(*) FROM torneosact t WHERE ' . $condCamp . ' AND COALESCE(t.invitaciones_despachadas, 0) = 1'
            )->fetchColumn();
            $pct = $totCam > 0 ? (int) round(100 * $desp / $totCam) : 100;
            $totDel = (int) $pdo->query('SELECT COUNT(*) FROM delegados WHERE activo = 1')->fetchColumn();
            $tg = (int) $pdo->query(
                "SELECT COUNT(*) FROM delegados WHERE activo = 1 AND telegram_chat_id IS NOT NULL AND TRIM(telegram_chat_id) <> ''"
            )->fetchColumn();

            return 'Invitaciones: ' . $pct . '% enviadas | Telegram: ' . $tg . '/' . $totDel . ' activos';
        } catch (Throwable $e) {
            error_log('[fvd_invitaciones_monitor_line] ' . $e->getMessage());

            return 'Invitaciones: — | Telegram: —';
        }
    }
}

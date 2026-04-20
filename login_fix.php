<?php

declare(strict_types=1);

/**
 * Login de emergencia: formulario mínimo sin layout público ni dependencias.
 * Envía POST a fvdmasteradmin/login.php (mismos campos que el login oficial).
 */
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso FVD — emergencia</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 22rem; }
        label { display: block; margin-top: 0.75rem; font-size: 0.9rem; }
        input { width: 100%; box-sizing: border-box; padding: 0.4rem 0.5rem; margin-top: 0.25rem; }
        button { margin-top: 1rem; padding: 0.5rem 1rem; width: 100%; cursor: pointer; }
        p.note { font-size: 0.8rem; color: #444; margin-top: 1.25rem; }
    </style>
</head>
<body>
    <h1 style="font-size:1.1rem;">FVD Master Admin (login de emergencia)</h1>
    <form method="post" action="fvdmasteradmin/login.php" autocomplete="on">
        <label for="email">Usuario (correo o usuario)</label>
        <input type="text" id="email" name="email" required autocomplete="username">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <button type="submit">Entrar</button>
    </form>
    <p class="note">Si puede usar el flujo normal, prefiera <a href="login.php">login.php</a> en la raíz del proyecto.</p>
</body>
</html>

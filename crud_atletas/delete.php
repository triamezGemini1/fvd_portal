<?php
require_once __DIR__ . '/../models/Atleta.php';

$atleta = new Atleta();
$id = $_GET['id'] ?? null;

if (!$id) {
    die("⚠️ ID inválido");
}

// Opcional: verificar si existe antes de borrar
$registro = $atleta->readOne($id);
if (!$registro) {
    die("⚠️ El atleta con ID $id no existe.");
}

// Confirmar y eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $atleta->id = $id;
    if ($atleta->delete()) {
        header("Location: index.php?msg=eliminado");
        exit;
    } else {
        echo "❌ Error al eliminar atleta.";
    }
}
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Eliminar Atleta</title>
</head>
<body>
<h1>🗑️ Eliminar Atleta</h1>
<p>¿Seguro que deseas eliminar al atleta <strong><?= htmlspecialchars($registro['nombre']) ?></strong> con cédula <strong><?= htmlspecialchars($registro['cedula']) ?></strong>?</p>

<form method="post">
    <button type="submit">Sí, eliminar</button>
    <a href="index.php">Cancelar</a>
</form>
</body>
</html>
<?php
$content = ob_get_clean();
include("../layout/layout.php");

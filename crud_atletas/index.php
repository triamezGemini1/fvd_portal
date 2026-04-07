<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/Atleta.php';
include_file("config/database.php");

// Instancia del modelo
$atleta = new Atleta();
$pdo = Database::getConnection();

// Obtener asociaciones para el filtro
$stmt = $pdo->query("SELECT id, nombre FROM asociaciones ORDER BY nombre ASC");
$asociaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configuración de paginación
$limit  = 20; // registros por página
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$page   = max($page, 1);
$offset = ($page - 1) * $limit;

// Filtros
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$asociacion_id = isset($_GET['asociacion']) && is_numeric($_GET['asociacion']) ? (int)$_GET['asociacion'] : 0;

// Construir query con filtros
$where = [];
$params = [];

if (!empty($buscar)) {
    $where[] = "(nombre LIKE ? OR cedula LIKE ? OR email LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($asociacion_id > 0) {
    $where[] = "asociacion = ?";
    $params[] = $asociacion_id;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Contar total con filtros
$sql_count = "SELECT COUNT(*) as total FROM atletas $where_clause";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total / $limit);

// Obtener atletas con filtros
$sql = "SELECT * FROM atletas $where_clause ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$atletas = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Lista de Atletas</title>
<style>
    select, #asociacion {
        background-color: #ffffff !important;
        color: #111827 !important;
        color-scheme: light;
    }
    select option, select optgroup {
        background-color: #ffffff;
        color: #111827;
    }
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; table-layout: auto; }
    th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: center; white-space: nowrap; }
    th { background: #333; color: #fff; }
    td img { max-width: 50px; max-height: 50px; border-radius: 50%; }
    .acciones a { margin: 0 4px; text-decoration: none; }
    nav ul { list-style:none; display:flex; gap:6px; justify-content:center; padding:0; margin-top:15px; }
    nav li { padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; }
    nav li a { text-decoration:none; color:#333; }
    nav li[style*="font-weight:bold"] { background:#333; color:#fff; }
</style>
</head>
<body>
<h1>Listado de Atletas</h1>

<!-- Filtros de búsqueda -->
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
    <form method="GET" action="" style="display: flex; gap: 10px; align-items: end;">
        <div style="flex: 1;">
            <label for="buscar" style="display: block; margin-bottom: 5px; font-weight: bold;">🔍 Buscar:</label>
            <input type="text" 
                   id="buscar" 
                   name="buscar" 
                   placeholder="Nombre, cédula o email..." 
                   value="<?= htmlspecialchars($buscar) ?>"
                   style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        
        <div style="flex: 1;">
            <label for="asociacion" style="display: block; margin-bottom: 5px; font-weight: bold;">🏢 Asociación:</label>
            <select id="asociacion" 
                    name="asociacion" 
                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                <option value="0">-- Todas las asociaciones --</option>
                <?php foreach ($asociaciones as $asoc): ?>
                    <option value="<?= $asoc['id'] ?>" <?= $asociacion_id == $asoc['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($asoc['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <button type="submit" style="padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                🔍 Filtrar
            </button>
            <a href="index.php" style="display: inline-block; padding: 8px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">
                🔄 Limpiar
            </a>
        </div>
    </form>
    
    <?php if (!empty($buscar) || $asociacion_id > 0): ?>
        <div style="margin-top: 10px; padding: 8px; background: #e3f2fd; border-radius: 4px;">
            <strong>📊 Resultados:</strong> <?= $total ?> atleta(s) encontrado(s)
            <?php if (!empty($buscar)): ?>
                <span style="background: #fff; padding: 2px 8px; border-radius: 3px; margin-left: 5px;">
                    Búsqueda: "<?= htmlspecialchars($buscar) ?>"
                </span>
            <?php endif; ?>
            <?php if ($asociacion_id > 0): ?>
                <?php 
                $asoc_nombre = '';
                foreach ($asociaciones as $asoc) {
                    if ($asoc['id'] == $asociacion_id) {
                        $asoc_nombre = $asoc['nombre'];
                        break;
                    }
                }
                ?>
                <span style="background: #fff; padding: 2px 8px; border-radius: 3px; margin-left: 5px;">
                    Asociación: <?= htmlspecialchars($asoc_nombre) ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<a href="create.php">➕ Crear Nuevo</a>
<br><br>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Cédula</th>
            <th>Nombre</th>
            <th>Sexo</th>
            <th>N° FVD</th>
            <th>Asociación</th>
            <th>Email</th>
            <th>Foto</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!empty($atletas)): ?>
        <?php foreach ($atletas as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['id']) ?></td>
            <td><?= htmlspecialchars($row['cedula']) ?></td>
            <td><?= htmlspecialchars($row['nombre']) ?></td>
            <td><?= htmlspecialchars($row['sexo']) ?></td>
            <td><?= htmlspecialchars($row['numfvd'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['asociacion'] ?? 'Sin asociación') ?></td>
            <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
            <td>
                <?php if (!empty($row['foto'])): ?>
                    <img src="uploads/<?= htmlspecialchars($row['foto']) ?>" alt="Foto">
                <?php else: ?>
                    <span class="text-muted">Sin foto</span>
                <?php endif; ?>
            </td>
            <td class="acciones">
                <a href="ver.php?id=<?= urlencode($row['id']) ?>">👁️ Ver</a>
                <a href="edit.php?id=<?= urlencode($row['id']) ?>">✏️ Editar</a>
                <a href="delete.php?id=<?= urlencode($row['id']) ?>" 
                   onclick="return confirm('¿Seguro que deseas eliminar este registro?')">🗑️ Eliminar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="9">⚠️ No hay registros</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- 📌 Paginador compacto -->
<?php if ($total_pages > 1): ?>
<nav>
    <ul>
        <!-- Botón primera página -->
        <?php if ($page > 3): ?>
            <li><a href="?page=1&buscar=<?= urlencode($buscar) ?>&asociacion=<?= $asociacion_id ?>">1</a></li>
            <?php if ($page > 4): ?>
                <li>...</li>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Páginas cercanas -->
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li style="<?= $i === $page ? 'font-weight:bold;' : '' ?>">
                <a href="?page=<?= $i ?>&buscar=<?= urlencode($buscar) ?>&asociacion=<?= $asociacion_id ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>

        <!-- Botón última página -->
        <?php if ($page < $total_pages - 2): ?>
            <?php if ($page < $total_pages - 3): ?>
                <li>...</li>
            <?php endif; ?>
            <li><a href="?page=<?= $total_pages ?>&buscar=<?= urlencode($buscar) ?>&asociacion=<?= $asociacion_id ?>"><?= $total_pages ?></a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>
</body>
</html>
<?php
$content = ob_get_clean();
include("../layout/layout.php");


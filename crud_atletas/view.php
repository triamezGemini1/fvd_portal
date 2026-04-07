<?php
require_once "models/Atleta.php";
require_once "../crud_asociaciones/models/Asociacion.php";

$title = "Ficha del Atleta";
$model = new Atleta();
$asociacionModel = new Asociacion();

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];
$atleta = $model->getById($id);

if (!$atleta) {
    die("Atleta no encontrado.");
}

// 🔎 Nombre de la asociación
$asociacion = $asociacionModel->getById($atleta['asociacion']);
$asociacionNombre = $asociacion ? $asociacion['nombre'] : "—";

ob_start();
?>
<h2 class="mb-4">👤 Ficha del Atleta</h2>

<div class="card shadow">
  <div class="card-body row">
    <div class="col-md-4 text-center">
      <?php if ($atleta['foto']): ?>
        <img src="../uploads/<?= htmlspecialchars($atleta['foto']) ?>" class="img-thumbnail mb-3" width="200">
      <?php else: ?>
        <img src="../assets/img/no-photo.png" class="img-thumbnail mb-3" width="200">
      <?php endif; ?>
    </div>
    <div class="col-md-8">
        <p><strong>Cédula:</strong> <?= htmlspecialchars($atleta['cedula'] ?? '') ?></p>
        <p><strong>Nombre:</strong> <?= htmlspecialchars($atleta['nombre'] ?? '') ?></p>
        <p><strong>Sexo:</strong> <?= isset($atleta['sexo']) ? ($atleta['sexo'] == 'M' ? 'Masculino' : 'Femenino') : '' ?></p>
        <p><strong>Fecha Nacimiento:</strong> <?= !empty($atleta['fechnac']) ? htmlspecialchars($atleta['fechnac']) : '' ?></p>
        <p><strong>Profesión:</strong> <?= htmlspecialchars($atleta['profesion'] ?? '') ?></p>
        <p><strong>Dirección:</strong> <?= htmlspecialchars($atleta['direccion'] ?? '') ?></p>
        <p><strong>Teléfono:</strong> <?= htmlspecialchars($atleta['celular'] ?? '') ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($atleta['email'] ?? '') ?></p>
        <p><strong>Asociación:</strong> <?= htmlspecialchars($asociacionNombre ?? '') ?></p>
    </div>
  </div>
</div>

<div class="mt-3">
  <a href="edit.php?id=<?= $atleta['id'] ?>" class="btn btn-primary">✏️ Editar</a>
  <a href="index.php" class="btn btn-secondary">⬅️ Volver</a>
</div>
<?php
$content = ob_get_clean();
include("../layout/layout.php");

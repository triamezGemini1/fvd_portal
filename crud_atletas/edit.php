<?php
require_once __DIR__ . '/../models/Atleta.php';
include_file("models/Asociacion.php");


$db = (new Database())->getConnection();

$asociaciones = $db->query(
    "SELECT id, nombre FROM asociaciones ORDER BY nombre"
)->fetchAll(PDO::FETCH_ASSOC);

$title = "Editar Atleta";

$model = new Atleta();
$msg = "";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];
$atleta = $model->readOne($id);

if (!$atleta) {
    echo "<p>No se encontró el atleta.</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    $data['foto'] = $atleta['foto'];
    $data['cedula_img'] = $atleta['cedula_img'];

   if (!is_dir("../uploads")) mkdir("../uploads", 0777, true);

    if (!empty($_FILES['foto']['name'])) {
        $filename = time() . "_" . basename($_FILES["foto"]["name"]);
        $target = "../uploads/" . $filename;
        if (!is_dir("../uploads")) mkdir("../uploads", 0777, true);
        move_uploaded_file($_FILES["foto"]["tmp_name"], $target);
        $data['foto'] = $filename;
    }

    if (!empty($_FILES['cedula_img']['name'])) {
        $filename = time() . "_" . basename($_FILES["cedula_img"]["name"]);
        $target = "../uploads/" . $filename;
        if (!is_dir("../uploads")) mkdir("../uploads", 0777, true);
        move_uploaded_file($_FILES["cedula_img"]["tmp_name"], $target);
        $data['cedula_img'] = $filename;
    }

    //$model = $stmt->fetch(PDO::FETCH_ASSOC);
    //$atleta->update($data);
    if ($model->update($id,$data)) {
        header("Location: index.php");
        exit;
    }
}


ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Editar Atleta</title>
<style>
    form { max-width: 50%; margin:auto; }
    label { display:block; margin-top:10px; }
    select, select.form-control {
        background-color: #ffffff;
        color: #111827;
        color-scheme: light;
    }
    select option, select optgroup {
        background-color: #ffffff;
        color: #111827;
    }
</style>


<?php if ($msg): ?>
    <p style="color:red;"><?= htmlspecialchars($msg) ?></p>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="row g-3">

    <div class="col-md-6">
      <label>Asociación</label>
      <select name="asociacion" class="form-control" required>
        <option value="">-- Seleccione --</option>
        <?php foreach ($asociaciones as $a): ?>
    <option value="<?= $a['id'] ?>"
      <?= ($a['id'] == $atleta['asociacion']) ? 'selected' : '' ?>>
      <?= htmlspecialchars($a['nombre']) ?>
    </option>
  <?php endforeach; ?>
      </select>
    </div>

   
    <div class="col-md-3">
    <label>Cédula:</label>
    <input type="text" name="cedula" id="cedula" class="form-control"  value="<?= htmlspecialchars($atleta['cedula']) ?>"required>
  </div>
  <div class="col-md-6">
    <label>Nombre</label>
    <input type="text" name="nombre" id="nombre" class="form-control"  value="<?= htmlspecialchars($atleta['nombre']) ?>" required>
  </div>
  <div class="col-md-3">
    <label>Sexo:</label>
    <select name="sexo" id="sexo" class="form-control"  value="<?= htmlspecialchars($atleta['sexo']) ?>" required>
      <option value="">Seleccione</option>
      <option value="M">Masculino</option>
      <option value="F">Femenino</option>
    </select>
  </div>
  <div class="col-md-3">
    <label>Fecha Nac:</label>
    <input type="date" name="fechnac" id="fechnac" class="form-control" value="<?= htmlspecialchars($atleta['fechnac']) ?>" >
  </div>
    <div class="col-md-4">
        <label>Celular:</label>
        <input type="text" name="celular"  class="form-control value="<?= htmlspecialchars($atleta['celular']) ?>" >
    </div>  
    <div class="col-md-5">
        <label>Email:</label>
        <input type="email" name="email"  class="form-control"  value="<?= htmlspecialchars($atleta['email']) ?>" >
    </div>  

    <div class="col-md-6">
        <label>Profesión:</label>
        <input type="text" name="profesion"  class="form-control" value=<?= htmlspecialchars($atleta['profesion']) ?>" >
    </div>  
    <div class="col-md-6">
        <label>Dirección:</label>
        <input type="text" name="direccion"  class="form-control"  value="<?= htmlspecialchars($atleta['direccion']) ?>" >
    </div>  
    <div class="col-md-6">
    <label>Foto del Atleta</label><br>
    <?php if ($atleta['foto']): ?>
      <div class="current-file mb-2">
        <strong>Archivo actual:</strong><br>
        <img src="../uploads/<?= htmlspecialchars($atleta['foto']) ?>" 
             style="max-width: 150px; max-height: 150px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" 
             class="mb-2"><br>
        <small class="text-muted"><i class="fas fa-file-image"></i> <?= htmlspecialchars($atleta['foto']) ?></small>
      </div>
    <?php endif; ?>
    <input type="file" name="foto" id="foto_input" accept="image/*" class="form-control">
    <div id="foto_preview" class="mt-2"></div>
  </div>
    <div class="col-md-6">
    <label>Foto de Cédula</label><br>
    <?php if ($atleta['cedula_img']): ?>
      <div class="current-file mb-2">
        <strong>Archivo actual:</strong><br>
        <img src="../uploads/<?= htmlspecialchars($atleta['cedula_img']) ?>" 
             style="max-width: 150px; max-height: 150px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" 
             class="mb-2"><br>
        <small class="text-muted"><i class="fas fa-file-image"></i> <?= htmlspecialchars($atleta['cedula_img']) ?></small>
      </div>
    <?php endif; ?>
    <input type="file" name="cedula_img" id="cedula_input" accept="image/*" class="form-control">
    <div id="cedula_preview" class="mt-2"></div>
  </div>

    <br><br>
    <input type="submit" value="Actualizar">
    <a href="index.php">Cancelar</a>
</form>

<script src="../assets/js/file-preview.js"></script>
<script>
// Vista previa de archivos
document.addEventListener('DOMContentLoaded', function() {
    window.filePreview.init('foto_input', 'foto_preview', 'image');
    window.filePreview.init('cedula_input', 'cedula_preview', 'image');
});
</script>
</body>
</html>
<?php
$content = ob_get_clean();
include("../layout/layout.php");
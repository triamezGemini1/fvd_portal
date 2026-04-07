<?php
require_once __DIR__ . "/../bootstrap.php";
require_once BASE_PATH . "/config/database.php";
require_once BASE_PATH . "/config/persona_database.php";
// Modelo
include_file("models/Atleta.php");
include_file("models/Asociacion.php");


$db = (new Database())->getConnection();

$asociaciones = $db->query(
    "SELECT id, nombre FROM asociaciones ORDER BY nombre"
)->fetchAll(PDO::FETCH_ASSOC);


$atleta = new Atleta();
$msg = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tzName = function_exists('env') ? (string) env('APP_TIMEZONE', 'America/Caracas') : 'America/Caracas';
    try {
        $tz = new DateTimeZone($tzName);
    } catch (Exception $e) {
        $tz = new DateTimeZone('UTC');
    }
    $hoyFvd = (new DateTimeImmutable('now', $tz))->format('Y-m-d');

    $data = [
        'cedula'      => $_POST['cedula'],
        'nombre'      => $_POST['nombre'],
        'sexo'        => $_POST['sexo'],
        'numfvd'      => $_POST['numfvd'] ?? 0,
        'asociacion'  => $_POST['asociacion'],
        'torneo_id'   => 0,
        'estatus'     => $_POST['estatus'] ?? 0,
        'afiliacion'  => 0,
        'anualidad'   => 0,
        'carnet'      => 0,
        'traspaso'    => 0,
        'inscripcion' => 0,
        'categ'       => $_POST['categ'] ?? 0,
        'profesion'   => $_POST['profesion'] ?? null,
        'direccion'   => $_POST['direccion'] ?? null,
        'celular'     => $_POST['celular'] ?? null,
        'email'       => $_POST['email'] ?? null,
        'fechnac'     => $_POST['fechnac'] ?? null,
        'fechfvd'     => $hoyFvd,
        'fechact'     => $hoyFvd,
        'foto'        => $_FILES['foto']['name'] ?? null,
        'cedula_img'  => $_FILES['cedula_img']['name'] ?? null
    ];
    // Foto opcional
    if (!empty($_FILES['foto']['name'])) {
        $dir = __DIR__ . "/uploads/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $filename = uniqid() . "_" . basename($_FILES['foto']['name']);
        move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $filename);
        $data['foto'] = $filename;
    } else {
        $data['foto'] = null;
    }
    // img cedula opcional
    if (!empty($_FILES['cedula_img']['name'])) {
        $dir = __DIR__ . "/uploads/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $filename = uniqid() . "_" . basename($_FILES['cedula_img']['name']);
        move_uploaded_file($_FILES['cedula_img']['tmp_name'], $dir . $filename);
        $data['cedula_img'] = $filename;
    } else {
        $data['cedula_img'] = null;
    }
    if ($atleta->create($data)) {
        header("Location: index.php?msg=creado");
        exit;
    } else {
        echo "❌ Error al crear.";
    }
}


ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Crear Atleta</title>
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
</head>
<body>
<h1>➕ Crear Nuevo Atleta</h1>

<?php if ($msg): ?>
    <p style="color:red;"><?= htmlspecialchars($msg) ?></p>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="row g-3">

    <div class="col-md-6">
      <label>Asociación</label>
      <select name="asociacion" class="form-control" required>
        <option value="">-- Seleccione --</option>
        <?php foreach ($asociaciones as $a): ?>
          <option value="<?= $a['id'] ?>"><?= $a['nombre'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>

   
    <div class="col-md-3">
    <label>Cédula:</label>
    <input type="text" name="cedula" id="cedula" class="form-control" required>
  </div>
  <div class="col-md-6">
    <label>Nombre</label>
    <input type="text" name="nombre" id="nombre" class="form-control" required>
  </div>
  <div class="col-md-3">
    <label>Sexo:</label>
    <select name="sexo" id="sexo" class="form-control" required>
      <option value="">Seleccione</option>
      <option value="M">Masculino</option>
      <option value="F">Femenino</option>
    </select>
  </div>
  <div class="col-md-3">
    <label>Fecha Nac:</label>
    <input type="date" name="fechnac" id="fechnac" class="form-control">
  </div>
    <div class="col-md-4">
        <label>Celular:</label>
        <input type="text" name="celular"  class="form-control" >
    </div>  
    <div class="col-md-5">
        <label>Email:</label>
        <input type="email" name="email"  class="form-control" >
    </div>  

    <div class="col-md-6">
        <label>Profesión:</label>
        <input type="text" name="profesion"  class="form-control" >
    </div>  
    <div class="col-md-6">
        <label>Dirección:</label>
        <input type="text" name="direccion"  class="form-control" >
    </div>  
    
    <div class="col-md-6">
        <label>Foto del Atleta:</label>
        <input type="file" name="foto" id="foto_input" accept="image/*" class="form-control">
        <div id="foto_preview" class="mt-2"></div>
    </div>  
    <div class="col-md-6">
        <label>Foto de Cédula:</label>
        <input type="file" name="cedula_img" id="cedula_input" accept="image/*" class="form-control">
        <div id="cedula_preview" class="mt-2"></div>
    </div>  

    <br><br>
    <input type="submit" value="Crear">
    <a href="index.php">Cancelar</a>
</form>
<script src="../assets/js/file-preview.js"></script>
<script>
// Vista previa de archivos
document.addEventListener('DOMContentLoaded', function() {
    window.filePreview.init('foto_input', 'foto_preview', 'image');
    window.filePreview.init('cedula_input', 'cedula_preview', 'image');
});

// Verificación de cédula
document.getElementById('cedula').addEventListener('blur', function() {
    const cedula = this.value.trim();
    const mensaje = document.getElementById('mensaje');

    if (!cedula) return; // si está vacío no hace nada

    fetch('./verificacedula.php?cedula=' + encodeURIComponent(cedula))
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              // ✅ Poblar campos
              document.getElementById('nombre').value = data.data.nombre || '';
              document.getElementById('sexo').value   = data.data.sexo   || '';
              document.getElementById('fechnac').value= data.data.fechnac|| '';
              mensaje.textContent = ''; // limpiar mensaje
          } else {
              // ⚠️ Mostrar advertencia
              mensaje.textContent = data.message || 'Error desconocido';
              // Limpiar campos si se desea
              document.getElementById('nombre').value = '';
              document.getElementById('sexo').value   = '';
              document.getElementById('fechnac').value= '';
          }
      })
      .catch(err => {
          mensaje.textContent = 'Error de conexión: ' + err;
      });
});
</script>
</body>
</html>
<?php
$content = ob_get_clean();
include("../layout/layout.php");

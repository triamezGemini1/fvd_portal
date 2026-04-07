<form method="POST" enctype="multipart/form-data" action="<?= isset($data) ? 'actions/atleta_update.php' : 'actions/atleta_create.php' ?>">
  <input type="hidden" name="id" value="<?= $data['id'] ?? '' ?>">

  <div class="mb-2">
    <label>Asociación</label>
    <select name="asociacion" class="form-control" required>
      <option value="">-- Seleccione --</option>
      <!-- Aquí iterar asociaciones -->
    </select>
  </div>

  <div class="mb-2">
    <label>Cédula</label>
    <input type="text" name="cedula" value="<?= $data['cedula'] ?? '' ?>" class="form-control" required>
  </div>

  <div class="mb-2">
    <label>Nombre Completo</label>
    <input type="text" name="nombre" value="<?= $data['nombre'] ?? '' ?>" class="form-control" required>
  </div>

  <div class="mb-2">
    <label>Sexo</label>
    <select name="sexo" class="form-control" required>
      <option value="M" <?= (isset($data['sexo']) && $data['sexo']=="M")?'selected':'' ?>>Masculino</option>
      <option value="F" <?= (isset($data['sexo']) && $data['sexo']=="F")?'selected':'' ?>>Femenino</option>
    </select>
  </div>

  <div class="mb-2">
    <label>Profesión</label>
    <input type="text" name="profesion" value="<?= $data['profesion'] ?? '' ?>" class="form-control">
  </div>

  <div class="mb-2">
    <label>Dirección</label>
    <input type="text" name="direccion" value="<?= $data['direccion'] ?? '' ?>" class="form-control">
  </div>

  <div class="mb-2">
    <label>Teléfono</label>
    <input type="text" name="celular" value="<?= $data['celular'] ?? '' ?>" class="form-control">
  </div>

  <div class="mb-2">
    <label>Email</label>
    <input type="email" name="email" value="<?= $data['email'] ?? '' ?>" class="form-control">
  </div>

  <div class="mb-2">
    <label>Fecha Nacimiento</label>
    <input type="date" name="fechnac" value="<?= $data['fechnac'] ?? '' ?>" class="form-control">
  </div>

  <div class="mb-2">
    <label>Foto</label>
    <input type="file" name="foto" class="form-control">
    <?php if (!empty($data['foto'])): ?>
      <img src="../upload/<?= $data['foto'] ?>" width="80" class="mt-2">
    <?php endif; ?>
  </div>

  <button type="submit" class="btn btn-success">💾 Guardar</button>
  <a href="index.php" class="btn btn-secondary">↩️ Cancelar</a>
</form>

<div class="mb-2">
  <label>Cédula</label>
  <input type="text" id="cedula" name="cedula" value="<?= $data['cedula'] ?? '' ?>" class="form-control" required>
  <small id="cedula-msg" class="text-danger"></small>
</div>

<script src="../assets/js/jquery.min.js"></script>
<script>
$("#cedula").on("blur", function() {
    let cedula = $(this).val();
    if (cedula.length > 0) {
        $.get("actions/buscar_persona.php", { cedula: cedula }, function(resp) {
            if (resp.success) {
                $("input[name='nombre']").val(resp.data.nombre);
                $("select[name='sexo']").val(resp.data.sexo);
                $("input[name='fechnac']").val(resp.data.fechnac);
            } else {
                $("#cedula-msg").text(resp.message);
            }
        }, "json");
    }
});
</script>

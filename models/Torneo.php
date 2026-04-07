<?php

// Incluimos bootstrap (subimos un nivel porque estamos en /models/)
require_once __DIR__ . "/../bootstrap.php";


// Incluimos la conexión a la base de datos

require_once BASE_PATH . "/config/database.php";

class Torneo {
    private $conn;
    private $table = "torneosact";

    public function __construct() {
        $this->conn = Database::getConnection();
    }


    public function getAll() {
        $sql = "SELECT torneo, nombre, lugar, fechator, invitacion, afiche FROM {$this->table} ORDER BY fechator DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE torneo=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table} 
        (organizacion_id, clavetor, nombre, lugar, fechator, tipo, clase, tiempo, puntos, rondas, estatus, costotor, ranking, pareclub, invitacion, afiche)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $data['organizacion_id'] ?? null,
            $data['clavetor'], $data['nombre'], $data['lugar'], $data['fechator'],
            $data['tipo'], $data['clase'], $data['tiempo'], $data['puntos'], $data['rondas'],
            $data['estatus'], $data['costotor'], $data['ranking'], $data['pareclub'],
            $data['invitacion'], $data['afiche']
        ]);
    }

    public function update($id, $data) {
        $sql = "UPDATE {$this->table} 
                SET organizacion_id=?, clavetor=?, nombre=?, lugar=?, fechator=?, tipo=?, clase=?, tiempo=?, puntos=?, rondas=?, estatus=?, costotor=?, ranking=?, pareclub=?, invitacion=?, afiche=? 
                WHERE torneo=?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $data['organizacion_id'] ?? null,
            $data['clavetor'], $data['nombre'], $data['lugar'], $data['fechator'],
            $data['tipo'], $data['clase'], $data['tiempo'], $data['puntos'], $data['rondas'],
            $data['estatus'], $data['costotor'], $data['ranking'], $data['pareclub'],
            $data['invitacion'], $data['afiche'], $id
        ]);
    }

    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE torneo=?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$id]);
    }



    public function generarClaveTorneo($fechaTorneo)
{
    // 1. Año de la fecha del torneo
    $anio = date('Y', strtotime($fechaTorneo));

    // 2. Contar torneos del mismo año
    $sql = "SELECT COUNT(*) FROM torneosact WHERE YEAR(fechator) = :anio";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':anio', $anio, PDO::PARAM_INT);
    $stmt->execute();
    $cantidad = $stmt->fetchColumn();

    // 3. Calcular siguiente consecutivo y formatearlo a 3 dígitos
    $consecutivo = str_pad($cantidad + 1, 3, '0', STR_PAD_LEFT);

    // 4. Retornar clave
    return $anio . '-' . $consecutivo;
}

}

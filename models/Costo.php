<?php

// Incluimos bootstrap (subimos un nivel porque estamos en /models/)
require_once __DIR__ . "/../bootstrap.php";


// Incluimos la conexión a la base de datos

require_once BASE_PATH . "/config/database.php";

class Costo {
    private $conn;
    private $table = "costos";

    public $id, $fecha, $afiliacion, $anualidad, $carnets, $traspasos, $inscripciones;

    public function __construct() {
        $this->conn = Database::getConnection();
    }


    public function getAll() {
        $sql = "SELECT * FROM {$this->table} ORDER BY fecha DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id=? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table} 
                (fecha, afiliacion, anualidad, carnets, traspasos, inscripciones) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $data['fecha'], $data['afiliacion'], $data['anualidad'],
            $data['carnets'], $data['traspasos'], $data['inscripciones']
        ]);
    }

    public function update($id, $data) {
        $sql = "UPDATE {$this->table}
                SET fecha=?, afiliacion=?, anualidad=?, carnets=?, traspasos=?, inscripciones=? 
                WHERE id=?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $data['fecha'], $data['afiliacion'], $data['anualidad'],
            $data['carnets'], $data['traspasos'], $data['inscripciones'], $id
        ]);
    }

    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id=?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$id]);
    }
}

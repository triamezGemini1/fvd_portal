<?php

// Incluimos bootstrap (subimos un nivel porque estamos en /models/)
require_once __DIR__ . "/../bootstrap.php";


// Incluimos la conexión a la base de datos

require_once BASE_PATH . "/config/database.php";

class Asociacion {
    private $conn;
    private $table = "asociaciones";

    public $id;
    public $nombre;
    public $direccion;
    public $telefono;
    public $email;
    public $numreg;
    public $delegado;
    public $estatus;
    public $logo;

    public function __construct() {
$this->conn = Database::getConnection();
    }

    public function getAll() {
        $sql = "SELECT * FROM {$this->table} ORDER BY nombre ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table} (nombre, direccion, telefono, email, numreg, delegado, estatus, logo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $data['nombre'], $data['direccion'], $data['telefono'], $data['email'],
            $data['numreg'], $data['delegado'], $data['estatus'], $data['logo']
        ]);
    }

    public function update($id, $data) {
        $sql = "UPDATE {$this->table}
                SET nombre=?, direccion=?, telefono=?, email=?, numreg=?, delegado=?, estatus=?, logo=?
                WHERE id=?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $data['nombre'], $data['direccion'], $data['telefono'], $data['email'],
            $data['numreg'], $data['delegado'], $data['estatus'], $data['logo'], $id
        ]);
    }

    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id=?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$id]);
    }



    // Obtener registros con paginación
    public function getPaginated($limit, $offset) {
        $query = "SELECT id, nombre, delegado, telefono, email, logo 
                  FROM {$this->table}
                  ORDER BY nombre ASC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Contar total de registros
    public function countAll() {
        $query = "SELECT COUNT(*) as total FROM {$this->table}";
        $stmt = $this->conn->query($query);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ?? 0;
    }
}


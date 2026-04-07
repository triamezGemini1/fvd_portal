<?php
// Incluimos bootstrap (subimos un nivel porque estamos en /models/)
require_once __DIR__ . "/../bootstrap.php";


// Incluimos la conexión a la base de datos

require_once BASE_PATH . "/config/database.php";

class Atleta {
    private $conn;
    private $table_name = "atletas";

    // ===============================
    // Propiedades de la tabla
    // ===============================
    public $id;
    public $cedula;
    public $nombre;
    public $sexo;
    public $numfvd;
    public $asociacion;
    public $torneo_id;
    public $estatus;
    public $afiliacion;
    public $anualidad;
    public $carnet;
    public $traspaso;
    public $inscripcion;
    public $categ;
    public $profesion;
    public $direccion;
    public $celular;
    public $email;
    public $fechnac;
    public $fechfvd;
    public $fechact;
    public $foto;
    public $cedula_img;
    public $created_at;
    public $updated_at;

    public function __construct() {
        // ✅ Ajustado: usamos getConnection() directamente
        $this->conn = Database::getConnection();
    }

    // ===============================
    // Métodos
    // ===============================

    /** 📋 Obtener todos los registros (sin paginar) */
    public function readAll() {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name}");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** 📊 Contar registros */
    public function countAll() {
        $query = "SELECT COUNT(*) as total FROM {$this->table_name}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0);
    }

    /** 📑 Paginación */
    public function getPaginated($limit, $offset) {
        $query = "SELECT a.id, a.cedula, a.nombre, a.numfvd, a.sexo, a.foto, 
                         s.nombre AS asociacion
                  FROM {$this->table_name} a
                  LEFT JOIN asociaciones s ON a.asociacion = s.id
                  ORDER BY a.id DESC
                  LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":limit", (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



// Listar atletas (datos clave para tabla)
public function getAll() {
    $query = "SELECT id, cedula, nombre, sexo, asociacion, torneo_id, estatus, categ, celular, email, foto
              FROM {$this->table_name} 
              ORDER BY nombre ASC";
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Crear con array de datos
public function create($data) {
    $query = "INSERT INTO {$this->table_name} 
        (cedula, nombre, sexo, numfvd, asociacion, torneo_id, estatus, afiliacion, anualidad, carnet,
         traspaso, inscripcion, categ, profesion, direccion, celular, email, fechnac, fechfvd, fechact, foto, cedula_img) 
        VALUES
        (:cedula, :nombre, :sexo, :numfvd, :asociacion, :torneo_id, :estatus, :afiliacion, :anualidad, :carnet,
         :traspaso, :inscripcion, :categ, :profesion, :direccion, :celular, :email, :fechnac, :fechfvd, :fechact, :foto, :cedula_img)";
    $stmt = $this->conn->prepare($query);
    return $stmt->execute($data);
}


// Actualizar con array de datos
public function update($id,$data) {
    $query = "UPDATE {$this->table_name} 
    SET nombre=?, sexo=?, asociacion=?, celular=?,
         profesion=?, direccion=?, 
        email=?, fechnac=?,  foto=?, cedula_img=? 
        WHERE id=?";

    $stmt = $this->conn->prepare($query);
    return $stmt->execute([
        $data['nombre'], $data['sexo'], $data['asociacion'],$data['celular'],
        $data['profesion'], $data['direccion'], $data['email'],$data['fechnac'],
        $data['foto'], $data['cedula_img'],  $id
     ]);    

}



    /** 🔍 Leer un atleta por ID */
    public function readOne($id) {
        $query = "SELECT a.*, s.nombre AS asociacion_nombre
                  FROM {$this->table_name} a
                  LEFT JOIN asociaciones s ON a.asociacion = s.id
                  WHERE a.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

   

    /** 🗑️ Eliminar atleta */
    public function delete() {
        $query = "DELETE FROM {$this->table_name} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}

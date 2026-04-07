<?php

// Incluimos bootstrap (subimos un nivel porque estamos en /models/)
require_once __DIR__ . "/../bootstrap.php";


// Incluimos la conexión a la base de datos

require_once BASE_PATH . "/config/database.php";

class DeudaAsociacion {
    private $conn;
    private $table = "deuda_asociaciones"; 
    public $torneo_id;
    public $asociacion_id;
    public $total_inscritos;
    public $monto_inscritos;
    public $total_afiliados;
    public $monto_afiliados;
    public $total_carnets;
    public $monto_carnets;
    public $total_traspasos;
    public $monto_traspasos;
    public $total_anualidad;
    public $monto_anualidad;
    public $monto_total;

    public function __construct() {
        $this->conn = Database::getConnection();
    }


    // Listar todas las deudas
    public function getAll() {
        $sql = "SELECT * FROM {$this->table} ORDER BY fecha_creacion DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Leer deuda específica
    public function getOne($torneo_id, $asociacion_id) {
        $sql = "SELECT * FROM {$this->table} WHERE torneo_id=? AND asociacion_id=? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$torneo_id, $asociacion_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear nueva deuda
    public function create() {
        $sql = "INSERT INTO {$this->table}
        (torneo_id, asociacion_id, total_inscritos, monto_inscritos, total_afiliados, monto_afiliados,
         total_carnets, monto_carnets, total_traspasos, monto_traspasos, total_anualidad, monto_anualidad, monto_total)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $this->torneo_id, $this->asociacion_id,
            $this->total_inscritos, $this->monto_inscritos,
            $this->total_afiliados, $this->monto_afiliados,
            $this->total_carnets, $this->monto_carnets,
            $this->total_traspasos, $this->monto_traspasos,
            $this->total_anualidad, $this->monto_anualidad,
            $this->monto_total
        ]);
    }

    // Actualizar deuda existente
    public function update() {
        $sql = "UPDATE {$this->table} SET
            total_inscritos=?, monto_inscritos=?, total_afiliados=?, monto_afiliados=?,
            total_carnets=?, monto_carnets=?, total_traspasos=?, monto_traspasos=?,
            total_anualidad=?, monto_anualidad=?, monto_total=?, fecha_actualizacion=NOW()
            WHERE torneo_id=? AND asociacion_id=?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $this->total_inscritos, $this->monto_inscritos,
            $this->total_afiliados, $this->monto_afiliados,
            $this->total_carnets, $this->monto_carnets,
            $this->total_traspasos, $this->monto_traspasos,
            $this->total_anualidad, $this->monto_anualidad,
            $this->monto_total,
            $this->torneo_id, $this->asociacion_id
        ]);
    }

    // Eliminar deuda
    public function delete($torneo_id, $asociacion_id) {
        $sql = "DELETE FROM {$this->table} WHERE torneo_id=? AND asociacion_id=?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$torneo_id, $asociacion_id]);
    }

    // 📌 Generar deudas desde atletas para un torneo específico
    public function generarDesdeAtletas($torneo_id = null) {
        // Condición para el torneo específico o todos
        $where_torneo = $torneo_id ? "AND a.torneo_id = ?" : "";
        
        $sql = "INSERT INTO deuda_asociaciones (
                    torneo_id, asociacion_id, 
                    total_inscritos, monto_inscritos, 
                    total_afiliados, monto_afiliados,
                    total_carnets, monto_carnets,
                    total_traspasos, monto_traspasos,
                    total_anualidad, monto_anualidad,
                    monto_total, fecha_creacion, fecha_actualizacion
                )
                SELECT 
                    a.torneo_id,
                    a.asociacion,
                    SUM(a.inscripcion = 1) AS total_inscritos,
                    SUM(a.inscripcion = 1) * c.inscripciones AS monto_inscritos,
                    SUM(a.afiliacion = 1) AS total_afiliados,
                    SUM(a.afiliacion = 1) * c.afiliacion AS monto_afiliados,
                    SUM(a.carnet = 1) AS total_carnets,
                    SUM(a.carnet = 1) * c.carnets AS monto_carnets,
                    SUM(a.traspaso = 1) AS total_traspasos,
                    SUM(a.traspaso = 1) * c.traspasos AS monto_traspasos,
                    SUM(a.anualidad = 1) AS total_anualidad,
                    SUM(a.anualidad = 1) * c.anualidad AS monto_anualidad,
                    (
                      SUM(a.inscripcion = 1) * c.inscripciones +
                      SUM(a.afiliacion = 1) * c.afiliacion +
                      SUM(a.carnet = 1) * c.carnets +
                      SUM(a.traspaso = 1) * c.traspasos +
                      SUM(a.anualidad = 1) * c.anualidad
                    ) AS monto_total,
                    NOW(), NOW()
                FROM atletas a
                CROSS JOIN (
                    SELECT * FROM costos ORDER BY fecha DESC LIMIT 1
                ) c
                WHERE a.torneo_id > 0 $where_torneo
                GROUP BY a.torneo_id, a.asociacion
                ON DUPLICATE KEY UPDATE
                    total_inscritos = VALUES(total_inscritos),
                    monto_inscritos = VALUES(monto_inscritos),
                    total_afiliados = VALUES(total_afiliados),
                    monto_afiliados = VALUES(monto_afiliados),
                    total_carnets = VALUES(total_carnets),
                    monto_carnets = VALUES(monto_carnets),
                    total_traspasos = VALUES(total_traspasos),
                    monto_traspasos = VALUES(monto_traspasos),
                    total_anualidad = VALUES(total_anualidad),
                    monto_anualidad = VALUES(monto_anualidad),
                    monto_total = VALUES(monto_total),
                    fecha_actualizacion = NOW()";

        $stmt = $this->conn->prepare($sql);
        
        if ($torneo_id) {
            return $stmt->execute([$torneo_id]);
        }
        
        return $stmt->execute();
    }
    
    // Verificar si un torneo está cerrado
    public function torneoEstaCerrado($torneo_id) {
        $sql = "SELECT estatus FROM torneosact WHERE torneo = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Asumiendo que estatus = 0 significa cerrado
        return $torneo && $torneo['estatus'] == 0;
    }


// Obtener el total acumulado de todas las deudas
public function getTotalDeudas() {
    $query = "SELECT 
                SUM(monto_total) AS total_general,
                SUM(total_inscritos) AS total_inscritos,
                SUM(total_afiliados) AS total_afiliados,
                SUM(total_carnets) AS total_carnets,
                SUM(total_traspasos) AS total_traspasos,
                SUM(total_anualidad) AS total_anualidad
              FROM {$this->table}";

    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}



}



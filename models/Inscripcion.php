<?php
require_once dirname(__FILE__) . '/../../config/database.php';

class Inscripcion {
    private $conn;
    public function __construct(){
        $this->conn = Database::getInstance()->getConnection();
    }

    public function getDisponibles($torneo_id, $asociacion_id){
        $sql = "SELECT id, cedula, nombre, carnet
                FROM atletas
                WHERE asociacion = ? AND (inscripcion = 0 OR torneo_id = 0)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$asociacion_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInscritos($torneo_id, $asociacion_id){
        $sql = "SELECT id, cedula, nombre, carnet
                FROM atletas
                WHERE asociacion = ? AND inscripcion = 1 AND torneo_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$asociacion_id, $torneo_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function inscribir($id, $torneo_id){
        $sql = "UPDATE atletas
                SET inscripcion = 1, torneo_id = ?
                WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$torneo_id, $id]);
    }

    public function retirar($id, $torneo_id){
        $sql = "UPDATE atletas
                SET inscripcion = 0, torneo_id = 0
                WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$id]);
    }
}

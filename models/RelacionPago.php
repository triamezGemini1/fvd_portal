<?php

// Incluimos bootstrap (subimos un nivel porque estamos en /models/)
require_once __DIR__ . "/../bootstrap.php";


// Incluimos la conexión a la base de datos

require_once BASE_PATH . "/config/database.php";

class RelacionPago {
    private $conn;
    private $table_name = "relacion_pagos";

    public $id;
    public $torneo_id;
    public $asociacion_id;
    public $secuencia;
    public $fecha;
    public $tasa_cambio;
    public $tipo_pago;
    public $moneda; // 'divisas' o 'Bs'
    public $monto_total; // Total en la moneda del pago (Bs para 'Bs', USD para 'divisas')
    public $monto_dolares; // Monto en USD
    public $referencia; // Referencia bancaria
    public $banco; // Nombre del banco
    public $observaciones;
    public $fecha_creacion;

    public function __construct() {
        $this->conn = Database::getConnection();
    }


    // Crear un nuevo pago
    public function create($data) {
        $query = "INSERT INTO {$this->table_name} 
                  (torneo_id, asociacion_id, secuencia, fecha, tasa_cambio, tipo_pago, moneda, monto_total, 
                   monto_dolares, referencia, banco, observaciones) 
                  VALUES (:torneo_id, :asociacion_id, :secuencia, :fecha, :tasa_cambio, :tipo_pago, :moneda, :monto_total,
                          :monto_dolares, :referencia, :banco, :observaciones)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":torneo_id", $data['torneo_id']);
        $stmt->bindParam(":asociacion_id", $data['asociacion_id']);
        $stmt->bindParam(":secuencia", $data['secuencia']);
        $stmt->bindParam(":fecha", $data['fecha']);
        $stmt->bindParam(":tasa_cambio", $data['tasa_cambio']);
        $stmt->bindParam(":tipo_pago", $data['tipo_pago']);
        $stmt->bindParam(":moneda", $data['moneda']);
        $stmt->bindParam(":monto_total", $data['monto_total']);
        $stmt->bindParam(":monto_dolares", $data['monto_dolares']);
        $stmt->bindParam(":referencia", $data['referencia']);
        $stmt->bindParam(":banco", $data['banco']);
        $stmt->bindParam(":observaciones", $data['observaciones']);

        return $stmt->execute();
    }

    // Obtener pago por ID
    public function getById($id) {
        $query = "SELECT * FROM {$this->table_name} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Actualizar pago
    public function update($data) {
        $query = "UPDATE {$this->table_name} 
                  SET torneo_id = :torneo_id, asociacion_id = :asociacion_id, secuencia = :secuencia, 
                      fecha = :fecha, tasa_cambio = :tasa_cambio, tipo_pago = :tipo_pago, 
                      moneda = :moneda, monto_total = :monto_total
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":torneo_id", $data['torneo_id']);
        $stmt->bindParam(":asociacion_id", $data['asociacion_id']);
        $stmt->bindParam(":secuencia", $data['secuencia']);
        $stmt->bindParam(":fecha", $data['fecha']);
        $stmt->bindParam(":tasa_cambio", $data['tasa_cambio']);
        $stmt->bindParam(":tipo_pago", $data['tipo_pago']);
        $stmt->bindParam(":moneda", $data['moneda']);
        $stmt->bindParam(":monto_total", $data['monto_total']);
        $stmt->bindParam(":id", $data['id'], PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Eliminar pago
    public function delete($id) {
        $query = "DELETE FROM {$this->table_name} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Listar todos los pagos
    public function getAll() {
        $query = "SELECT * FROM {$this->table_name} ORDER BY fecha DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener el total acumulado de todos los pagos (en USD)
    // Suma monto_dolares (abonos en USD), NO monto_total (que puede estar en Bs)
public function getTotalPagos() {
    $query = "SELECT 
                SUM(monto_dolares) AS total_pagos,
                COUNT(*) AS cantidad_pagos
              FROM {$this->table_name}";

    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

}

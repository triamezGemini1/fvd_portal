<?php
/**
 * Configuración de conexión a la base de datos externa "persona"
 */
class PersonaDatabase {
    private $host = "localhost";     // ⚠️ cambiar según servidor
    private $db_name = "persona";    // nombre de la base de datos externa
    private $username = "root";      // usuario de la BD
    private $password = "";          // contraseña de la BD
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8mb4");
        } catch (PDOException $exception) {
            echo "Error de conexión a BD persona: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

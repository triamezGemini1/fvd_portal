<?php
/**
 * Clase AccessDatabase - Conexión y operaciones con bases de datos MySQL y Access
 * Permite migrar datos desde MySQL hacia Access de forma segura y eficiente
 * 
 * Requisitos:
 * - PHP con soporte COM (solo Windows)
 * - Microsoft Access Database Engine (ACE.OLEDB.12.0 o JET.OLEDB.4.0)
 * - PDO para MySQL
 */

class AccessDatabase {
    private $conn;
    private $db_path;
    private $db_type; // 'mysql' o 'access'
    private $errors = [];
    private $logs = [];
    
    // Configuración de conexión MySQL
    private $mysql_config = [
        'host' => 'localhost',
        'database' => 'convernva',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ];

    /**
     * Constructor
     * @param string $tipo - 'mysql' para origen, 'access' para destino
     * @param string $access_path - Ruta al archivo .mdb/.accdb (solo si tipo='access')
     */
    public function __construct($tipo = 'mysql', $access_path = null) {
        $this->db_type = $tipo;
        
        try {
            if ($tipo === 'mysql') {
                $this->conectarMySQL();
            } else {
                if (empty($access_path)) {
                    throw new Exception("Debe especificar la ruta del archivo Access");
                }
                $this->db_path = $access_path;
                $this->conectarAccess();
            }
            
            $this->addLog("Conexión establecida correctamente a {$tipo}");
        } catch (Exception $e) {
            $this->addError("Error al conectar: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Conectar a MySQL
     */
    private function conectarMySQL() {
        $dsn = "mysql:host={$this->mysql_config['host']};dbname={$this->mysql_config['database']};charset={$this->mysql_config['charset']}";
        $this->conn = new PDO($dsn, $this->mysql_config['username'], $this->mysql_config['password']);
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * Conectar a Access
     */
    private function conectarAccess() {
        // Verificar que el archivo existe
        if (!file_exists($this->db_path)) {
            throw new Exception("No se encontró el archivo Access en: " . $this->db_path);
        }
        
        // Verificar que estamos en Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            throw new Exception("La conexión a Access solo está disponible en Windows");
        }
        
        // Intentar conexión con ACE.OLEDB.12.0 (Access 2007+)
        try {
            $this->conn = new COM("ADODB.Connection");
            $provider = "Provider=Microsoft.ACE.OLEDB.12.0;Data Source=" . realpath($this->db_path) . ";";
            $this->conn->Open($provider);
            $this->addLog("Conectado con ACE.OLEDB.12.0");
        } catch (Exception $e) {
            // Si falla, intentar con JET.OLEDB.4.0 (Access 97-2003)
            try {
                $this->conn = new COM("ADODB.Connection");
                $provider = "Provider=Microsoft.Jet.OLEDB.4.0;Data Source=" . realpath($this->db_path) . ";";
                $this->conn->Open($provider);
                $this->addLog("Conectado con JET.OLEDB.4.0");
            } catch (Exception $e2) {
                throw new Exception("No se pudo conectar a Access. Verifique que tenga instalado el Access Database Engine");
            }
        }
        
        if (!$this->conn) {
            throw new Exception("Error al establecer conexión con Access");
        }
    }

    /**
     * Obtener conexión activa
     */
    public function getConnection() {
        return $this->conn;
    }

    /**
     * Ejecutar consulta SELECT
     * @param string $sql - Consulta SQL
     * @return array - Resultados
     */
    public function query($sql) {
        try {
            if ($this->db_type === 'mysql') {
                $stmt = $this->conn->query($sql);
                return $stmt->fetchAll();
            } else {
                $recordset = $this->conn->Execute($sql);
                $results = [];
                
                if ($recordset && !$recordset->EOF) {
                    while (!$recordset->EOF) {
                        $row = [];
                        for ($i = 0; $i < $recordset->Fields->Count; $i++) {
                            $field = $recordset->Fields($i);
                            $row[$field->Name] = $field->Value;
                        }
                        $results[] = $row;
                        $recordset->MoveNext();
                    }
                }
                
                return $results;
            }
        } catch (Exception $e) {
            $this->addError("Error en query: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Ejecutar comando (INSERT, UPDATE, DELETE)
     * @param string $sql - Comando SQL
     * @return int - Número de filas afectadas
     */
    public function execute($sql) {
        try {
            if ($this->db_type === 'mysql') {
                return $this->conn->exec($sql);
            } else {
                // En Access, ejecutar y capturar registros afectados
                $recordsAffected = 0;
                $this->conn->Execute($sql, $recordsAffected);
                return $recordsAffected;
            }
        } catch (Exception $e) {
            $this->addError("Error en execute: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Verificar si una tabla existe
     * @param string $tabla - Nombre de la tabla
     * @return bool
     */
    public function tablaExiste($tabla) {
        try {
            if ($this->db_type === 'mysql') {
                $stmt = $this->conn->query("SHOW TABLES LIKE '$tabla'");
                return $stmt->rowCount() > 0;
            } else {
                $rs = $this->conn->OpenSchema(20); // adSchemaTables
                while (!$rs->EOF) {
                    if ($rs->Fields("TABLE_NAME")->Value == $tabla) {
                        return true;
                    }
                    $rs->MoveNext();
                }
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtener información de columnas de una tabla
     * @param string $tabla - Nombre de la tabla
     * @return array - Array de columnas con sus propiedades
     */
    public function getColumnasTabla($tabla) {
        try {
            if ($this->db_type === 'mysql') {
                $stmt = $this->conn->query("DESCRIBE `$tabla`");
                return $stmt->fetchAll();
            } else {
                $rs = $this->conn->OpenSchema(4, [$tabla]); // adSchemaColumns
                $columnas = [];
                while (!$rs->EOF) {
                    $columnas[] = [
                        'Field' => $rs->Fields("COLUMN_NAME")->Value,
                        'Type' => $rs->Fields("DATA_TYPE")->Value
                    ];
                    $rs->MoveNext();
                }
                return $columnas;
            }
        } catch (Exception $e) {
            $this->addError("Error al obtener columnas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Limpiar tabla (DELETE FROM)
     * @param string $tabla - Nombre de la tabla
     * @return bool
     */
    public function limpiarTabla($tabla) {
        try {
            // Access usa corchetes o nada, MySQL usa comillas invertidas
            if ($this->db_type === 'mysql') {
                $this->execute("DELETE FROM `$tabla`");
            } else {
                $this->execute("DELETE FROM [$tabla]");
            }
            $this->addLog("Tabla $tabla limpiada");
            return true;
        } catch (Exception $e) {
            $this->addError("Error al limpiar tabla $tabla: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Migrar datos de MySQL a Access
     * @param AccessDatabase $dbMySQL - Instancia de AccessDatabase conectada a MySQL
     * @param string $tabla - Nombre de la tabla
     * @param array $filtro - Condición WHERE (opcional)
     * @param bool $limpiar_destino - Si debe limpiar la tabla destino antes
     * @param array $mapeo_columnas - Mapeo de columnas origen => destino (opcional)
     * @return array - ['success' => bool, 'registros' => int, 'errores' => array]
     */
    public function migrarDesdeMySQL($dbMySQL, $tabla, $filtro = [], $limpiar_destino = false, $mapeo_columnas = []) {
        $resultado = [
            'success' => false,
            'registros' => 0,
            'errores' => [],
            'tiempo' => 0
        ];
        
        $inicio = microtime(true);
        
        try {
            // Verificar que la tabla existe en MySQL
            if (!$dbMySQL->tablaExiste($tabla)) {
                throw new Exception("La tabla $tabla no existe en MySQL");
            }
            
            // Verificar que la tabla existe en Access
            if (!$this->tablaExiste($tabla)) {
                throw new Exception("La tabla $tabla no existe en Access");
            }
            
            // Limpiar tabla destino si se solicita
            if ($limpiar_destino) {
                $this->limpiarTabla($tabla);
            }
            
            // Construir consulta con filtro
            $where_clause = '';
            if (!empty($filtro)) {
                $where_clause = " WHERE " . implode(' AND ', $filtro);
            }
            
            // Obtener datos desde MySQL
            $sql = "SELECT * FROM `$tabla`" . $where_clause;
            $this->addLog("Consultando: $sql");
            $datos = $dbMySQL->query($sql);
            
            if (empty($datos)) {
                $this->addLog("No hay registros para migrar en tabla $tabla");
                $resultado['success'] = true;
                $resultado['tiempo'] = round(microtime(true) - $inicio, 2);
                return $resultado;
            }
            
            // Insertar datos en Access
            $registros_insertados = 0;
            $lote_size = 50; // Insertar en lotes para mejor rendimiento
            $lote_actual = [];
            
            foreach ($datos as $registro) {
                try {
                    // Aplicar mapeo de columnas si existe
                    if (!empty($mapeo_columnas)) {
                        $registro_mapeado = [];
                        foreach ($registro as $col => $val) {
                            $col_destino = isset($mapeo_columnas[$col]) ? $mapeo_columnas[$col] : $col;
                            $registro_mapeado[$col_destino] = $val;
                        }
                        $registro = $registro_mapeado;
                    }
                    
                    // Construir INSERT
                    $columnas = array_keys($registro);
                    $valores = array_map(function($val) {
                        if (is_null($val)) return 'NULL';
                        if (is_numeric($val)) return $val;
                        // Escapar comillas simples y limpiar caracteres especiales
                        $val = str_replace("'", "''", $val);
                        return "'" . $val . "'";
                    }, array_values($registro));
                    
                    $sql_insert = "INSERT INTO `$tabla` (" . implode(', ', $columnas) . ") VALUES (" . implode(', ', $valores) . ")";
                    
                    $this->execute($sql_insert);
                    $registros_insertados++;
                } catch (Exception $e) {
                    $resultado['errores'][] = "Error al insertar registro: " . $e->getMessage();
                }
            }
            
            $resultado['success'] = true;
            $resultado['registros'] = $registros_insertados;
            $resultado['tiempo'] = round(microtime(true) - $inicio, 2);
            $this->addLog("Migrados $registros_insertados registros de tabla $tabla en {$resultado['tiempo']}s");
            
        } catch (Exception $e) {
            $resultado['errores'][] = $e->getMessage();
            $this->addError("Error en migración: " . $e->getMessage());
            $resultado['tiempo'] = round(microtime(true) - $inicio, 2);
        }
        
        return $resultado;
    }

    /**
     * Crear tabla en Access basada en estructura de MySQL
     * @param AccessDatabase $dbMySQL - Instancia conectada a MySQL
     * @param string $tabla - Nombre de la tabla
     * @return bool
     */
    public function crearTablaDesdeMySQL($dbMySQL, $tabla) {
        try {
            // Obtener estructura de la tabla en MySQL
            $columnas = $dbMySQL->getColumnasTabla($tabla);
            
            if (empty($columnas)) {
                throw new Exception("No se pudo obtener la estructura de la tabla $tabla");
            }
            
            // Mapeo de tipos MySQL a Access
            $mapeo_tipos = [
                'int' => 'INTEGER',
                'bigint' => 'INTEGER',
                'tinyint' => 'BYTE',
                'varchar' => 'VARCHAR',
                'text' => 'LONGTEXT',
                'date' => 'DATETIME',
                'datetime' => 'DATETIME',
                'decimal' => 'DECIMAL',
                'float' => 'SINGLE',
                'double' => 'DOUBLE'
            ];
            
            // Construir CREATE TABLE
            $campos_sql = [];
            foreach ($columnas as $col) {
                $tipo_mysql = strtolower($col['Type']);
                $tipo_access = 'VARCHAR(255)'; // Por defecto
                
                // Buscar tipo correspondiente
                foreach ($mapeo_tipos as $mysql => $access) {
                    if (strpos($tipo_mysql, $mysql) !== false) {
                        $tipo_access = $access;
                        break;
                    }
                }
                
                $campos_sql[] = "`{$col['Field']}` $tipo_access";
            }
            
            $sql_create = "CREATE TABLE `$tabla` (" . implode(', ', $campos_sql) . ")";
            $this->execute($sql_create);
            $this->addLog("Tabla $tabla creada en Access");
            
            return true;
        } catch (Exception $e) {
            $this->addError("Error al crear tabla: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener lista de tablas disponibles
     * @return array
     */
    public function getTablas() {
        try {
            if ($this->db_type === 'mysql') {
                $stmt = $this->conn->query("SHOW TABLES");
                $tablas = [];
                while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    $tablas[] = $row[0];
                }
                return $tablas;
            } else {
                $rs = $this->conn->OpenSchema(20); // adSchemaTables
                $tablas = [];
                while (!$rs->EOF) {
                    $tipo = $rs->Fields("TABLE_TYPE")->Value;
                    if ($tipo == "TABLE") {
                        $tablas[] = $rs->Fields("TABLE_NAME")->Value;
                    }
                    $rs->MoveNext();
                }
                return $tablas;
            }
        } catch (Exception $e) {
            $this->addError("Error al obtener tablas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar registros en una tabla
     * @param string $tabla - Nombre de la tabla
     * @param string $where - Condición WHERE opcional
     * @return int
     */
    public function contarRegistros($tabla, $where = '') {
        try {
            // Access usa corchetes o nada, MySQL usa comillas invertidas
            if ($this->db_type === 'mysql') {
                $sql = "SELECT COUNT(*) as total FROM `$tabla`";
            } else {
                $sql = "SELECT COUNT(*) as total FROM [$tabla]";
            }
            
            if (!empty($where)) {
                $sql .= " WHERE $where";
            }
            
            if ($this->db_type === 'mysql') {
                $stmt = $this->conn->query($sql);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return (int)$result['total'];
            } else {
                $rs = $this->conn->Execute($sql);
                return (int)$rs->Fields('total')->Value;
            }
        } catch (Exception $e) {
            $this->addError("Error al contar registros: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener información del sistema
     */
    public function getSystemInfo() {
        $info = [
            'db_type' => $this->db_type,
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'com_enabled' => class_exists('COM')
        ];
        
        if ($this->db_type === 'mysql') {
            $info['db_name'] = $this->mysql_config['database'];
            $info['db_host'] = $this->mysql_config['host'];
            $info['connected'] = $this->conn !== null;
        } else {
            $info['db_path'] = $this->db_path;
            $info['file_exists'] = file_exists($this->db_path);
            $info['file_size'] = file_exists($this->db_path) ? filesize($this->db_path) : 0;
            $info['file_size_mb'] = round($info['file_size'] / 1024 / 1024, 2);
            $info['last_modified'] = file_exists($this->db_path) ? date('Y-m-d H:i:s', filemtime($this->db_path)) : 'N/A';
            $info['real_path'] = realpath($this->db_path);
            $info['connected'] = $this->conn !== null;
        }
        
        return $info;
    }

    /**
     * Agregar mensaje de error
     */
    private function addError($mensaje) {
        $this->errors[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'mensaje' => $mensaje
        ];
    }

    /**
     * Agregar mensaje de log
     */
    private function addLog($mensaje) {
        $this->logs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'mensaje' => $mensaje
        ];
    }

    /**
     * Obtener errores
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Obtener logs
     */
    public function getLogs() {
        return $this->logs;
    }

    /**
     * Cerrar conexión
     */
    public function close() {
        if ($this->conn) {
            try {
                if ($this->db_type === 'mysql') {
                    $this->conn = null;
                } else {
                    $this->conn->Close();
                }
                $this->addLog("Conexión cerrada");
            } catch (Exception $e) {
                // Ignorar errores al cerrar
            }
        }
    }

    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}



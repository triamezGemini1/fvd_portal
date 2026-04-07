<?php
require_once "../config/persona_database.php"; // conexión externa
require_once "../config/database.php";        // conexión principal

header('Content-Type: application/json');

if (!isset($_GET['cedula']) || empty($_GET['cedula'])) {
    echo json_encode(['success' => false, 'message' => 'Cédula requerida']);
    exit;
}

$cedula = $_GET['cedula'];

try {
    // 1. Verificar si ya existe en atletas o atletas_tmp
    $db = (new Database())->getConnection();
    $check = $db->prepare("SELECT id FROM atletas WHERE cedula = ? UNION SELECT id FROM atletas_tmp WHERE cedula = ?");
    $check->execute([$cedula, $cedula]);

    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => '⚠️ La cédula ya está registrada en el sistema']);
        exit;
    }

    // 2. Buscar en base de datos externa
    $database = new PersonaDatabase();
    $conn = $database->getConnection();

    $sql = "SELECT Nombre1, Nombre2, Apellido1, Apellido2, FNac, Sexo, NAC
            FROM dpersona
            WHERE IDusuario = :cedula LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':cedula', $cedula, PDO::PARAM_STR);
    $stmt->execute();

    $persona = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($persona) {
        $nombreCompleto = trim(
            ($persona['Nombre1'] ?? '') . ' ' .
            ($persona['Nombre2'] ?? '') . ' ' .
            ($persona['Apellido1'] ?? '') . ' ' .
            ($persona['Apellido2'] ?? '')
        );
        $nombreCompleto = preg_replace('/\s+/', ' ', $nombreCompleto);

        echo json_encode([
            'success' => true,
            'data' => [
                'nombre'   => $nombreCompleto,
                'sexo'     => $persona['Sexo'],
                'fechnac'  => $persona['FNac'] ? date('Y-m-d', strtotime($persona['FNac'])) : null,
                'nacionalidad' => $persona['NAC'] ?? 'V'
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No encontrado en dpersona']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

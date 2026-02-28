<?php
/* =============================================
   FYLCAD — Registrar actividad
   Archivo: registrar_actividad.php
   Método: POST (JSON)
============================================= */
session_start();
require_once 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok' => false]);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { echo json_encode(['ok' => false]); exit; }

$tiposValidos = [
    'proyecto_creado', 'proyecto_actualizado', 'proyecto_archivado',
    'cotizacion_generada', 'archivo_exportado', 'login', 'plan_cambiado'
];

$tipo        = $body['tipo']        ?? '';
$descripcion = $body['descripcion'] ?? '';
$proyectoId  = $body['proyecto_id'] ?? null;

if (!in_array($tipo, $tiposValidos)) {
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $stmt = getDB()->prepare("
        INSERT INTO actividad (usuario_id, proyecto_id, tipo, descripcion)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['usuario_id'],
        $proyectoId,
        $tipo,
        substr($descripcion, 0, 255)
    ]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false]);
}
<?php
/* =============================================
   FYLCAD — Guardar proyecto en DB
   Archivo: guardar_proyecto.php
   Método: POST (JSON)
============================================= */
session_start();
require_once 'config/db.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok' => false, 'error' => 'No autenticado.']);
    exit;
}

$usuarioId  = $_SESSION['usuario_id'];
$usuarioPlan = $_SESSION['usuario_plan'] ?? 'free';

// Recibir JSON del cuerpo
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos.']);
    exit;
}

$nombre      = trim($body['nombre']      ?? 'Proyecto sin nombre');
$puntos      = $body['puntos']           ?? [];
$metricas    = $body['metricas']         ?? [];
$cotizacion  = $body['cotizacion']       ?? [];
$archivoNom  = trim($body['archivo']     ?? '');

// Validar límite plan free
if ($usuarioPlan === 'free' && count($puntos) > 50) {
    echo json_encode([
        'ok'    => false,
        'error' => 'El plan Free admite hasta 50 puntos. Actualiza a Premium para guardar archivos más grandes.'
    ]);
    exit;
}

if (empty($puntos)) {
    echo json_encode(['ok' => false, 'error' => 'No hay puntos para guardar.']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    // 1) Crear proyecto
    $stmt = $db->prepare("
        INSERT INTO proyectos (
            usuario_id, nombre, archivo_nombre,
            total_puntos, total_triangulos,
            area_m2, perimetro_m, volumen_m3,
            cota_min, cota_max, desnivel,
            centroide_x, centroide_y, centroide_z,
            estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completo')
    ");
    $stmt->execute([
        $usuarioId,
        $nombre,
        $archivoNom,
        count($puntos),
        $metricas['triangulos']  ?? 0,
        $metricas['area']        ?? 0,
        $metricas['perimetro']   ?? 0,
        $metricas['volumen']     ?? 0,
        $metricas['zMin']        ?? 0,
        $metricas['zMax']        ?? 0,
        $metricas['desnivel']    ?? 0,
        $metricas['centroideX']  ?? 0,
        $metricas['centroideY']  ?? 0,
        $metricas['centroideZ']  ?? 0,
    ]);
    $proyectoId = $db->lastInsertId();

    // 2) Guardar CSV de puntos
    $csv = "X,Y,Z\n";
    foreach ($puntos as $p) {
        $csv .= "{$p['x']},{$p['y']},{$p['z']}\n";
    }
    $stmt2 = $db->prepare("
        INSERT INTO archivos (proyecto_id, nombre, contenido, tamano_kb)
        VALUES (?, ?, ?, ?)
    ");
    $stmt2->execute([
        $proyectoId,
        $archivoNom ?: 'coordenadas.csv',
        $csv,
        round(strlen($csv) / 1024, 2)
    ]);

    // 3) Guardar cotización si existe
    if (!empty($cotizacion)) {
        $stmt3 = $db->prepare("
            INSERT INTO cotizaciones (
                proyecto_id, usuario_id,
                tarifa_tierra, tarifa_nivelacion, tarifa_cerramiento,
                costo_tierra, costo_nivelacion, costo_cerramiento, total
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt3->execute([
            $proyectoId,
            $usuarioId,
            $cotizacion['tarifaTierra']      ?? 8.5,
            $cotizacion['tarifaNivelacion']  ?? 3.2,
            $cotizacion['tarifaCerramiento'] ?? 45,
            $cotizacion['costoTierra']       ?? 0,
            $cotizacion['costoNivelacion']   ?? 0,
            $cotizacion['costoCerramiento']  ?? 0,
            $cotizacion['total']             ?? 0,
        ]);
    }

    // 4) Registrar actividad
    $stmt4 = $db->prepare("
        INSERT INTO actividad (usuario_id, proyecto_id, tipo, descripcion)
        VALUES (?, ?, 'proyecto_creado', ?)
    ");
    $stmt4->execute([
        $usuarioId,
        $proyectoId,
        "Proyecto \"{$nombre}\" guardado con " . count($puntos) . " puntos."
    ]);

    $db->commit();

    echo json_encode([
        'ok'         => true,
        'proyecto_id'=> $proyectoId,
        'mensaje'    => "Proyecto \"{$nombre}\" guardado correctamente."
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['ok' => false, 'error' => 'Error al guardar. Intenta de nuevo.']);
}
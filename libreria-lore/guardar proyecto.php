<?php
/* =============================================
   FYLCAD — Guardar / actualizar proyecto en DB
   Archivo: guardar_proyecto.php · Método: POST (JSON)
============================================= */
session_start();
require_once 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok'=>false,'error'=>'No autenticado.']); exit;
}

$usuarioId   = $_SESSION['usuario_id'];
$usuarioPlan = $_SESSION['usuario_plan'] ?? 'free';

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    echo json_encode(['ok'=>false,'error'=>'Datos inválidos.']); exit;
}

$nombre     = trim($body['nombre']    ?? 'Proyecto sin nombre');
$puntos     = $body['puntos']         ?? [];
$metricas   = $body['metricas']       ?? [];
$cotizacion = $body['cotizacion']     ?? [];
$archivoNom = trim($body['archivo']   ?? 'coordenadas.csv');
$proyId     = isset($body['proyecto_id']) ? (int)$body['proyecto_id'] : null;

// Límite plan free
if ($usuarioPlan === 'free' && count($puntos) > 2000) {
    echo json_encode(['ok'=>false,'error'=>'El plan Free admite hasta 2000 puntos. Actualiza a Premium.']); exit;
}
if (empty($puntos)) {
    echo json_encode(['ok'=>false,'error'=>'No hay puntos para guardar.']); exit;
}

// Construir CSV completo con todos los campos
$csv = "N,X,Y,Z,DESCRIPCION\n";
foreach ($puntos as $p) {
    $n    = isset($p['n'])    ? $p['n']    : '';
    $x    = isset($p['x'])    ? $p['x']    : 0;
    $y    = isset($p['y'])    ? $p['y']    : 0;
    $z    = isset($p['z'])    ? $p['z']    : 0;
    $desc = isset($p['desc']) ? str_replace([",","\n","\r"], [" "," "," "], $p['desc']) : '';
    $csv .= "{$n},{$x},{$y},{$z},{$desc}\n";
}

try {
    $db = getDB();
    $db->beginTransaction();

    if ($proyId) {
        // ── ACTUALIZAR proyecto existente ──
        // Verificar que pertenece al usuario
        $check = $db->prepare("SELECT id FROM proyectos WHERE id=? AND usuario_id=?");
        $check->execute([$proyId, $usuarioId]);
        if (!$check->fetch()) {
            $db->rollBack();
            echo json_encode(['ok'=>false,'error'=>'Proyecto no encontrado.']); exit;
        }
        $stmt = $db->prepare("
            UPDATE proyectos SET
                nombre=?, archivo_nombre=?,
                total_puntos=?, total_triangulos=?,
                area_m2=?, perimetro_m=?, volumen_m3=?,
                cota_min=?, cota_max=?, desnivel=?,
                centroide_x=?, centroide_y=?, centroide_z=?,
                actualizado_en=NOW(), estado='completo'
            WHERE id=? AND usuario_id=?
        ");
        $stmt->execute([
            $nombre, $archivoNom,
            count($puntos),       $metricas['triangulos']  ?? 0,
            $metricas['area']     ?? 0, $metricas['perimetro'] ?? 0,
            $metricas['volumen']  ?? 0,
            $metricas['zMin']     ?? 0, $metricas['zMax']      ?? 0,
            $metricas['desnivel'] ?? 0,
            $metricas['centroideX'] ?? 0, $metricas['centroideY'] ?? 0,
            $metricas['centroideZ'] ?? 0,
            $proyId, $usuarioId
        ]);
        // Actualizar CSV
        $db->prepare("DELETE FROM archivos WHERE proyecto_id=?")->execute([$proyId]);
        $proyectoId = $proyId;
        $accion = "actualizado";
    } else {
        // ── CREAR proyecto nuevo ──
        $stmt = $db->prepare("
            INSERT INTO proyectos (
                usuario_id, nombre, archivo_nombre,
                total_puntos, total_triangulos,
                area_m2, perimetro_m, volumen_m3,
                cota_min, cota_max, desnivel,
                centroide_x, centroide_y, centroide_z,
                estado
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'completo')
        ");
        $stmt->execute([
            $usuarioId, $nombre, $archivoNom,
            count($puntos),       $metricas['triangulos']  ?? 0,
            $metricas['area']     ?? 0, $metricas['perimetro'] ?? 0,
            $metricas['volumen']  ?? 0,
            $metricas['zMin']     ?? 0, $metricas['zMax']      ?? 0,
            $metricas['desnivel'] ?? 0,
            $metricas['centroideX'] ?? 0, $metricas['centroideY'] ?? 0,
            $metricas['centroideZ'] ?? 0,
        ]);
        $proyectoId = $db->lastInsertId();
        $accion = "guardado";
    }

    // Insertar CSV (siempre fresco)
    $db->prepare("INSERT INTO archivos (proyecto_id, nombre, contenido, tamano_kb) VALUES (?,?,?,?)")
       ->execute([$proyectoId, $archivoNom, $csv, round(strlen($csv)/1024, 2)]);

    // Cotización: upsert
    if (!empty($cotizacion)) {
        $db->prepare("DELETE FROM cotizaciones WHERE proyecto_id=?")->execute([$proyectoId]);
        $db->prepare("
            INSERT INTO cotizaciones (
                proyecto_id, usuario_id,
                tarifa_tierra, tarifa_nivelacion, tarifa_cerramiento,
                costo_tierra, costo_nivelacion, costo_cerramiento, total
            ) VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([
            $proyectoId, $usuarioId,
            $cotizacion['tarifaTierra']      ?? 21800,
            $cotizacion['tarifaNivelacion']  ?? 860,
            $cotizacion['tarifaCerramiento'] ?? 29370,
            $cotizacion['costoTierra']       ?? 0,
            $cotizacion['costoNivelacion']   ?? 0,
            $cotizacion['costoCerramiento']  ?? 0,
            $cotizacion['total']             ?? 0,
        ]);
    }

    // Actividad
    $db->prepare("
        INSERT INTO actividad (usuario_id, proyecto_id, tipo, descripcion)
        VALUES (?, ?, 'proyecto_creado', ?)
    ")->execute([
        $usuarioId, $proyectoId,
        "Proyecto \"{$nombre}\" {$accion} con " . count($puntos) . " puntos."
    ]);

    $db->commit();

    echo json_encode([
        'ok'          => true,
        'proyecto_id' => $proyectoId,
        'accion'      => $accion,
        'mensaje'     => "Proyecto \"{$nombre}\" {$accion} correctamente.",
    ]);

} catch (Exception $e) {
    if($db->inTransaction()) $db->rollBack();
    echo json_encode(['ok'=>false,'error'=>'Error al guardar: ' . $e->getMessage()]);
}
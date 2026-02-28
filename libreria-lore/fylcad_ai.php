<?php
/* =============================================
   FYLCAD — Asistente IA
   Archivo: fylcad_ai.php
   Ubicación: C:/xampp/htdocs/FYLCAD/fylcad_ai.php
   API: Anthropic Claude
============================================= */
session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$mensaje   = trim($body['mensaje']   ?? '');
$historial = $body['historial']      ?? [];
$pagina    = trim($body['pagina']    ?? 'general');

if (!$mensaje) {
    echo json_encode(['error' => 'Mensaje vacío.']);
    exit;
}

// ── PON AQUÍ TU API KEY DE ANTHROPIC ──────────
$apiKey = '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';
// ───────────────────────────────────────────────

$contextos = [
    'dashboard'     => 'El usuario está en el Panel principal de FYLCAD. Puede ver sus estadísticas, acceder al Módulo 3D, ver proyectos y cotizaciones.',
    'proyecto'      => 'El usuario está en el Módulo 3D de FYLCAD. Aquí puede cargar coordenadas topográficas (CSV, TXT), visualizar el terreno en 3D, calcular superficies y volúmenes.',
    'mis_proyectos' => 'El usuario está en su lista de proyectos. Puede ver, abrir o eliminar proyectos guardados.',
    'perfil'        => 'El usuario está en su perfil. Puede cambiar su nombre, contraseña y foto de perfil.',
    'planes'        => 'El usuario está viendo los planes de FYLCAD: Free y Premium. El plan Premium incluye puntos ilimitados y exportación PDF.',
    'cotizacion'    => 'El usuario está generando una cotización a partir de datos topográficos. Puede configurar precios unitarios y exportar el presupuesto.',
    'index'         => 'El usuario está en la página principal de FYLCAD (landing page).',
    'general'       => 'El usuario está usando FYLCAD, una plataforma SaaS de topografía digital.',
];

$ctxPagina = $contextos[$pagina] ?? $contextos['general'];

$systemPrompt = <<<PROMPT
Eres FYLIA, el asistente inteligente de FYLCAD — una plataforma SaaS de topografía digital.

Tu personalidad:
- Amigable, directo y técnicamente preciso
- Usas lenguaje claro, sin jerga innecesaria
- Cuando puedes, guías al usuario paso a paso
- Eres conciso: respuestas cortas y útiles, no párrafos largos
- Si no sabes algo específico de FYLCAD, lo dices honestamente

Contexto actual: {$ctxPagina}

Sobre FYLCAD:
- Plataforma web para procesar datos topográficos y generar cotizaciones de obra
- Módulo 3D: carga coordenadas (CSV/TXT), visualiza en 3D, calcula superficies y volúmenes
- Genera presupuestos automáticos con precios unitarios
- Plan Free: funciones básicas con límite de puntos
- Plan Premium: puntos ilimitados, exportar PDF, soporte prioritario
- Secciones: Dashboard, Módulo 3D, Mis Proyectos, Perfil, Planes

Formato de respuesta:
- Usa texto simple, sin markdown complejo
- Para pasos usa: 1. 2. 3.
- Para listas usa: bullet item
- Máximo 150 palabras por respuesta
- Si el usuario hace algo incorrecto, explica cómo corregirlo
PROMPT;

$messages = [];

$historialLimitado = array_slice($historial, -20);
foreach ($historialLimitado as $msg) {
    if (isset($msg['role'], $msg['content']) &&
        in_array($msg['role'], ['user', 'assistant']) &&
        strlen($msg['content']) < 2000) {
        $messages[] = [
            'role'    => $msg['role'],
            'content' => $msg['content']
        ];
    }
}

$messages[] = ['role' => 'user', 'content' => $mensaje];

$payload = json_encode([
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 400,
    'system'     => $systemPrompt,
    'messages'   => $messages,
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT        => 20,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['error' => 'No se pudo conectar con el asistente. Intenta de nuevo.']);
    exit;
}

$data = json_decode($response, true);

// Mostrar error detallado para depuración
if ($httpCode !== 200 || empty($data['content'][0]['text'])) {
    $errorMsg = $data['error']['message'] ?? 'Error desconocido';
    echo json_encode(['error' => 'Error Anthropic: ' . $errorMsg]);
    exit;
}

echo json_encode([
    'respuesta' => $data['content'][0]['text'],
    'tokens'    => $data['usage']['output_tokens'] ?? 0,
]);
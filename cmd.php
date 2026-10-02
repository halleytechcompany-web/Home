<?php
// cmd.php | Guarda la orden del panel y el estado que reporta el ESP-01.
// Súbelo al hosting; necesita permiso de escritura en su carpeta (crea state.json).

const DEVICE_KEY   = 'clave_del_chip';
const PANEL_KEY    = 'clave_del_panel';
const ALLOW_ORIGIN = '';   // solo si el panel está en otro sitio, ej: 'https://usuario.github.io'
const FILE         = __DIR__ . '/state.json';

header('Cache-Control: no-store');
if (ALLOW_ORIGIN !== '') header('Access-Control-Allow-Origin: ' . ALLOW_ORIGIN);

// Lee, modifica y guarda el estado con bloqueo para que no se pisen chip y panel.
function with_state(callable $fn): array {
    $f = fopen(FILE, 'c+');
    flock($f, LOCK_EX);
    $s = json_decode(stream_get_contents($f), true);
    if (!is_array($s)) $s = ['cmd' => '0', 'state' => '0', 'seen' => 0];
    $s = $fn($s);
    ftruncate($f, 0);
    rewind($f);
    fwrite($f, json_encode($s));
    flock($f, LOCK_UN);
    fclose($f);
    return $s;
}

$k = $_REQUEST['k'] ?? '';

if (hash_equals(DEVICE_KEY, (string)$k)) {
    // El chip pregunta: ?k=...&s=<estado actual 0|1>  ->  responde la orden "0" o "1"
    $s = with_state(function ($s) {
        if (isset($_GET['s']) && in_array($_GET['s'], ['0', '1'], true)) $s['state'] = $_GET['s'];
        $s['seen'] = time();
        return $s;
    });
    header('Content-Type: text/plain');
    echo $s['cmd'];
    exit;
}

if (hash_equals(PANEL_KEY, (string)$k)) {
    // El panel (POST): k=...&c=0|1 envía una orden; sin "c" solo consulta.
    $s = with_state(function ($s) {
        if (isset($_POST['c']) && in_array($_POST['c'], ['0', '1'], true)) $s['cmd'] = $_POST['c'];
        return $s;
    });
    header('Content-Type: application/json');
    echo json_encode([
        'cmd'   => $s['cmd'],
        'state' => $s['state'],
        'ago'   => $s['seen'] ? time() - $s['seen'] : null,
    ]);
    exit;
}

http_response_code(403);
echo 'no';

<?php
/************  DB CONFIG — EDIT  ************/
$DB_HOST = "localhost";
$DB_NAME = "u383530867_NinoSensor";
$DB_USER = "u383530867_db_NinoSensor";
$DB_PASS = "Billydaman040!";
/********************************************/

// TEMP DEBUG (remove after it works)
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

function fail($code, $msg) { http_response_code($code); echo $msg . "\n"; exit; }

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER, $DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
  );
} catch (Throwable $e) { fail(500, "DB connection failed: ".$e->getMessage()); }

$blob = null;

if (!empty($_POST['b']))       $blob = $_POST['b'];
elseif (!empty($_GET['b']))    $blob = $_GET['b'];
elseif (!empty($_GET['m']))    $blob = $_GET['m'];
else {
  $qs = $_SERVER['QUERY_STRING'] ?? '';
  if ($qs !== '' && strpos($qs, '&') === false) $blob = $qs;
}

$node = $temp = $hum = $time = null;

if ($blob !== null) {
  $raw_blob = $blob; // debug copy
  // normalize
  $blob = urldecode($blob);
  $blob = str_replace(' ', '+', $blob);
  // if URL-safe (- and _ present, but no +/), convert
  if (strpbrk($blob, '-_') && !strpbrk($blob, '+/')) $blob = strtr($blob, '-_', '+/');
  // add padding
  if (($m4 = strlen($blob) % 4) !== 0) $blob .= str_repeat('=', 4 - $m4);

  $decoded = base64_decode($blob, true);

  // DEBUG dump (visit with &debug=1 to see details)
  if (isset($_GET['debug'])) {
    echo "RAW_QUERY_STRING: ", ($_SERVER['QUERY_STRING'] ?? ''), "\n";
    echo "RAW_BLOB:         ", $raw_blob, "\n";
    echo "BLOB_NORMALIZED:  ", $blob, "\n";
    echo "DECODED:\n", ($decoded === false ? '[DECODE FAILED]' : $decoded), "\n";
  }

  if ($decoded === false) fail(400, "Invalid Base64 payload.");

  parse_str($decoded, $m);
  if (isset($_GET['debug'])) { echo "PARSED:\n"; print_r($m); }

  $node = $m['node'] ?? $m['node_name'] ?? $m['nodeId'] ?? null;
  $temp = $m['temperature'] ?? $m['temp'] ?? $m['nodeTemp'] ?? null;
  $hum  = $m['humidity'] ?? $m['hum'] ?? $m['nodeHum'] ?? $m['nodeHumidity'] ?? null;
  $time = $m['time'] ?? $m['time_received'] ?? $m['timeReceived'] ?? null;
} else {
  $node = $_REQUEST['node'] ?? $_REQUEST['node_name'] ?? $_REQUEST['nodeId'] ?? null;
  $temp = $_REQUEST['temperature'] ?? $_REQUEST['temp'] ?? $_REQUEST['nodeTemp'] ?? null;
  $hum  = $_REQUEST['humidity'] ?? $_REQUEST['hum'] ?? null;
  $time = $_REQUEST['time'] ?? $_REQUEST['time_received'] ?? $_REQUEST['timeReceived'] ?? null;
}

/* -------------------- 2) Validate -------------------- */
if ($node === null || $temp === null) {
  fail(400, "Missing required fields. Need node + temperature. (humidity required by your DB)");
}
if ($hum === null) {
  fail(400, "Missing humidity in payload. Use one of: humidity, hum, nodeHum, nodeHumidity");
}

if (!is_numeric($temp)) fail(400, "temperature must be numeric.");
if (!is_numeric($hum))  fail(400, "humidity must be numeric.");

$useNow = true;
if ($time !== null && trim($time) !== '') {
  $timeRaw = $time;                          // keep for error message
  // normalize: remove weird characters, trim, and collapse multiple spaces
  $time = preg_replace('/[^\d:\-\sT]/u', '', $time);  // keep digits, dash, colon, space, T
  $time = preg_replace('/\s+/', ' ', trim($time));    // collapse spaces

  // accept "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DD HH:MM" or ISO "YYYY-MM-DDTHH:MM(:SS)"
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $time)
     ?: DateTime::createFromFormat('Y-m-d H:i',    $time);

  // if they sent ISO "YYYY-MM-DDTHH:MM:SS", replace T with space and try again
  if (!$dt && strpos($time, 'T') !== false) {
    $try = str_replace('T', ' ', $time);
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $try)
       ?: DateTime::createFromFormat('Y-m-d H:i',    $try);
    if ($dt) { $time = $try; }
  }

  // final fallback: strtotime (handles most sane strings)
  if (!$dt && strtotime($time) !== false) {
    $dt = new DateTime($time);
  }

  if (!$dt) {
    fail(400, "Invalid time format '$timeRaw'. Use 'YYYY-MM-DD HH:MM[:SS]'.");
  }

  $time  = $dt->format('Y-m-d H:i:s');
  $useNow = false;
}


/* -------------------- 3) Ensure node exists -------------------- */
try {
  $chk = $pdo->prepare("SELECT 1 FROM sensor_register WHERE node_name = :n LIMIT 1");
  $chk->execute([':n' => $node]);
  if (!$chk->fetch()) fail(404, "Node '$node' not found in sensor_register.");
} catch (Throwable $e) { fail(500, "Select failed: ".$e->getMessage()); }

/* -------------------- 4) Insert -------------------- */
try {
  $sql = "INSERT INTO sensor_data (node_name, time_received, temperature, humidity)
        VALUES (:node, ".($useNow ? "NOW()" : ":time").", :t, :h)
        ON DUPLICATE KEY UPDATE
          temperature = VALUES(temperature),
          humidity    = VALUES(humidity)";

  $stmt = $pdo->prepare($sql);
  $params = [
    ':node' => $node,
    ':t'    => (float)$temp,
    ':h'    => $hum !== null ? (float)$hum : null
  ];
  if (!$useNow) $params[':time'] = $time;
  $stmt->execute($params);
} catch (Throwable $e) { fail(500, "Insert failed: ".$e->getMessage()); }

/* -------------------- 5) Done -------------------- */
echo "nodeId: $node\n";
echo "nodeTemp: ".$temp."\n";
echo "timeReceived: ".($useNow ? "(server NOW())" : $time)."\n";
echo "New record created successfully\n";

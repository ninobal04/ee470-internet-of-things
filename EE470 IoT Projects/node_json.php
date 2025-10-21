<?php
// --- DB creds same as index.php ---
$DB_HOST = "localhost";
$DB_NAME = "u383530867_NinoSensor";
$DB_USER = "u383530867_db_NinoSensor";
$DB_PASS = "Billydaman040!";

$node = isset($_GET['node']) ? $_GET['node'] : 'node_1';

header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER, $DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
  );

  $stmt = $pdo->prepare("
    SELECT
      node_name,
      DATE_FORMAT(time_received, '%Y-%m-%d %H:%i:%s') AS time_received,
      temperature,
      humidity
    FROM sensor_data
    WHERE node_name = :node
    ORDER BY time_received ASC
  ");
  $stmt->execute([':node' => $node]);
  $rows = $stmt->fetchAll();

  echo json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}

<?php
/************  CONFIG   ************/
$DB_HOST = "localhost";
$DB_NAME = "u383530867_NinoSensor";
$DB_USER = "u383530867_db_NinoSensor";
$DB_PASS = "Billydaman040!";
$AVG_NODE = "node_1";
$TEMP_UNIT = "°F";
$HUM_UNIT  = "%";
$CHART_NODE = "node_1";
$TEMP_UNIT  = "°C";
$SENSOR_DATA_TIME_COL = "time_received";
$JOIN_ON = ['sensor_data' => 'node_name', 'sensor_register' => 'node_name'];
/************  END CONFIG  ************/

function fatal($msg) {
  http_response_code(500);
  echo "<pre style='color:#b00020;font:14px/1.4 monospace'>Error: " . htmlspecialchars($msg) . "</pre>";
  exit;
}

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER, $DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
  );
} catch (Throwable $e) { fatal("DB connection failed. ".$e->getMessage()); }

/* ---------- helpers ---------- */
function fetchAll($pdo, $sql, $params = []) {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}
function render_table($title, $rows) {
  if (empty($rows)) {
    echo "<h2>$title</h2><div class='card'><em>No rows found.</em></div>";
    return;
  }
  echo "<h2>$title</h2>";
  echo "<div class='card'><table>";
  // headers
  echo "<thead><tr>";
  foreach (array_keys($rows[0]) as $col) {
    echo "<th>".htmlspecialchars($col)."</th>";
  }
  echo "</tr></thead><tbody>";
  // rows
  foreach ($rows as $r) {
    echo "<tr>";
    foreach ($r as $v) {
      if (is_null($v)) {
  $v = "—";
} elseif (is_numeric($v)) {
  $v = number_format((float)$v, 2);
}
echo "<td>".htmlspecialchars($v)."</td>";

    }
    echo "</tr>";
  }
  echo "</tbody></table></div>";
}

/* ---------- sanity checks ---------- */
$validCol = fn($s) => (bool)preg_match('/^[A-Za-z0-9_]+$/', $s);
if (!$validCol($SENSOR_DATA_TIME_COL)) fatal("Invalid time column name.");
if (!$validCol($JOIN_ON['sensor_data']) || !$validCol($JOIN_ON['sensor_register'])) {
  fatal("Invalid join column names.");
}

/* ---------- list columns (for pretty output) ---------- */
$cols_register = fetchAll($pdo, "SHOW COLUMNS FROM sensor_register");
$cols_data     = fetchAll($pdo, "SHOW COLUMNS FROM sensor_data");

if (!$cols_register || !$cols_data) fatal("Could not read table definitions.");
$register_cols = array_map(fn($c) => $c['Field'], $cols_register);
$data_cols     = array_map(fn($c) => $c['Field'], $cols_data);

/* ---------- 1) Registered Sensor Nodes (sorted by name if present) ---------- */
$node_name_col = in_array('node_name', $register_cols) ? 'node_name' : $register_cols[0]; // best guess for “Name”
$register_sql  = "SELECT ".implode(",", array_map(fn($c)=>"`$c`",$register_cols))."
                  FROM sensor_register
                  ORDER BY `$node_name_col` ASC";
$registered_rows = fetchAll($pdo, $register_sql);

/* ---------- 2) Data Received (JOIN + sort by Node name then Time) ---------- */
$join_left  = $JOIN_ON['sensor_data'];
$join_right = $JOIN_ON['sensor_register'];
if (!in_array($join_left, $data_cols))    fatal("Join column '$join_left' not in sensor_data.");
if (!in_array($join_right, $register_cols)) fatal("Join column '$join_right' not in sensor_register.");
if (!in_array($SENSOR_DATA_TIME_COL, $data_cols)) fatal("Time column '$SENSOR_DATA_TIME_COL' not in sensor_data.");

$select_parts = [];
// Prefer to display a human-readable name if available
$node_label_sql = in_array('node_name', $register_cols)
  ? "`sr`.`node_name` AS `Node`"
  : "`sr`.`$join_right` AS `Node`";
$select_parts[] = $node_label_sql;

// include all sensor_data columns
foreach ($data_cols as $dc) {
  $select_parts[] = "`sd`.`$dc`";
}
$select_clause = implode(", ", $select_parts);

$data_sql = "
  SELECT
    sr.node_name        AS `Node`,
    sd.time_received    AS `Time`,
    sd.temperature      AS `Temperature`,
    sd.humidity         AS `Humidity`
  FROM sensor_data sd
  JOIN sensor_register sr
    ON sd.node_name = sr.node_name
  ORDER BY sr.node_name ASC, sd.time_received ASC
";
$data_rows = fetchAll($pdo, $data_sql);

/* ---------- 4) Chart data for one node (time vs temperature) ---------- */
$chart_sql = "
  SELECT
    DATE_FORMAT(sd.time_received, '%Y-%m-%d %H:%i') AS tlabel,
    sd.temperature AS temp
  FROM sensor_data sd
  WHERE sd.node_name = :node
  ORDER BY sd.time_received ASC
";
$chart_rows = fetchAll($pdo, $chart_sql, [':node' => $CHART_NODE]);

$labels = array_map(fn($r) => $r['tlabel'], $chart_rows);
$temps  = array_map(fn($r) => is_null($r['temp']) ? null : round((float)$r['temp'], 2), $chart_rows);


$avg_sql = "
  SELECT
    AVG(sd.temperature) AS avg_temp,
    AVG(sd.humidity)    AS avg_hum
  FROM sensor_data sd
  WHERE sd.node_name = :node
";
$avg_row = fetchAll($pdo, $avg_sql, [":node" => $AVG_NODE]);
$avg_temp = $avg_row && $avg_row[0]['avg_temp'] !== null ? number_format((float)$avg_row[0]['avg_temp'], 2) : "—";
$avg_hum  = $avg_row && $avg_row[0]['avg_hum']  !== null ? number_format((float)$avg_row[0]['avg_hum'],  2) : "—";

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>SSU IoT Lab</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { --brand:#98c051; --brand-dark:#7da23f; --ink:#1a1a1a; }
  *{box-sizing:border-box}
  body{
    margin:0; color:var(--ink); font:16px/1.45 -apple-system,Segoe UI,Roboto,system-ui,sans-serif;
    /* grid paper background */
    background:
      linear-gradient(transparent 31px,#dfe8d8 32px) 0 0/32px 32px,
      linear-gradient(90deg,transparent 31px,#dfe8d8 32px) 0 0/32px 32px,
      #f6fbef;
  }
  .wrap{max-width:520px; margin:28px auto 40px; background:#fff; border:2px solid #8fb763; border-radius:6px; padding:22px 20px}
  h1{
    text-align:center; font-weight:800; letter-spacing:.2px; margin:4px 0 14px;
    font-size:44px; line-height:1.05;
  }
  .subtitle{ text-align:center; color:#49563a; margin:0 0 12px; font-size:20px; }
  h2{ text-align:center; margin:18px 0 10px; color:#37472a; }
  table{ width:100%; border-collapse:collapse; table-layout:fixed; }
  th,td{ padding:10px 12px; }
  th{
    background:var(--brand); color:#fff; text-align:left; font-weight:800; border:2px solid #8fb763;
  }
  td{
    background:#f1f7e8; border:2px solid #8fb763;
  }
    .avgline{
  margin: 6px 4px 0;
  text-align:center;
  font-size:16px;
  color:#2e3b25;
}
  /* tighten spacing like the example */
  .table-block{ margin:10px 0 20px; }

    <style>
  /* ... your existing table styles ... */

  /* === Chart styling === */
  .chart-frame {
    border: 2px solid #000;
    padding: 8px;
    background:
      linear-gradient(transparent 31px,#ececec 32px) 0 0/32px 32px,
      linear-gradient(90deg,transparent 31px,#ececec 32px) 0 0/32px 32px,
      #fff;
    max-width: 620px;
    margin: 6px auto 18px;
  }
</style>


  </style>
</head>
<body>
  <div class="wrap">
    <h1>Welcome to<br>SSU IoT Lab</h1>
    <div class="subtitle">Registered Sensor Nodes</div>
    <!-- Registered table -->
    <?php render_table("", $registered_rows); ?>

    <h2>Data Received</h2>
    <!-- Data table -->
    <?php render_table("", $data_rows); ?>
    <p class="avgline">The Average Temperature for <?php echo htmlspecialchars($AVG_NODE); ?> has been: <strong><?php echo $avg_temp . " " . $TEMP_UNIT; ?></strong></p>
<p class="avgline">The Average Humidity for <?php echo htmlspecialchars($AVG_NODE); ?> has been: <strong><?php echo $avg_hum . " " . $HUM_UNIT; ?></strong></p>

<h3>Temperature Data – Sensor <?php echo htmlspecialchars($CHART_NODE); ?></h3>

<div class="chart-frame">
  <canvas id="tempChart" height="200"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const labels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
  const temps  = <?php echo json_encode($temps,  JSON_UNESCAPED_UNICODE); ?>;

  const ctx = document.getElementById('tempChart').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Sensor Node <?php echo htmlspecialchars($CHART_NODE); ?>',
        data: temps,
        backgroundColor: 'rgba(152, 192, 81, 0.5)',   // ✅ light green fill
        borderColor: 'rgba(152, 192, 81, 1)',         // ✅ darker green border
        borderWidth: 1,
        barPercentage: 0.6,
        categoryPercentage: 0.8
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      plugins: {
        title: { display: false },
        legend: {
          display: true,
          position: 'top',
          align: 'end',
          labels: { color: '#333' }
        }
      },
      layout: { padding: { top: 10, right: 16, bottom: 10, left: 6 } },
      scales: {
        x: {
          title: { display: true, text: 'Time', color: '#333' },
          ticks: { autoSkip: true, maxRotation: 0, color: '#333' },
          grid: { color: '#d9d9d9', borderColor: '#000' }
        },
        y: {
          beginAtZero: true,
          title: { display: true, text: 'Temperature (<?php echo $TEMP_UNIT; ?>)', color: '#333' },
          ticks: { color: '#333' },
          grid: { color: '#d9d9d9', borderColor: '#000' }
        }
      }
    }
  });
</script>


  </div>
</body>
</html>

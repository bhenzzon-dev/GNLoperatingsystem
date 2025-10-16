<?php
session_start();
require_once 'db_connect.php';

$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selectedGroup = isset($_GET['group']) && is_numeric($_GET['group']) ? (int)$_GET['group'] : null;

$conditionReleasedSummary = $selectedDate ? "AND DATE(released_date) = '$selectedDate'" : "";
$conditionOthers = $selectedDate ? "AND DATE(released_date) = '$selectedDate'" : "";

if ($selectedGroup) {
  $conditionReleasedSummary .= " AND group_number = $selectedGroup";
  $conditionOthers .= " AND group_number = $selectedGroup";
}


// Fetch purchase order entries, ordered by po_number to group easily
$poQuery = "SELECT * FROM released_summary WHERE 1=1 $conditionReleasedSummary ORDER BY po_number, id";
$poResult = $conn->query($poQuery);


$poGroups = [];  // Array to hold grouped data

if ($poResult->num_rows > 0) {
    while ($row = $poResult->fetch_assoc()) {
        $po_number = $row['po_number'];
        // Group items under their PO number
        if (!isset($poGroups[$po_number])) {
            $poGroups[$po_number] = [
                'items' => [],
                'total' => 0,
                'particulars' => $row['particulars'],  // Add this line
            ];
        }
        
        $poGroups[$po_number]['items'][] = $row;
        $poGroups[$po_number]['total'] += $row['total_price'];  // assuming you have total_price column per item
    }
}
$projects = [];

foreach ($poGroups as $po_number => $poData) {
    $projectName = $poData['items'][0]['ship_project_name'];
    if (!isset($projects[$projectName])) {
        $projects[$projectName] = [];
    }
    $projects[$projectName][$po_number] = $poData;
}

$notifCount = 0;
$notifSql = "SELECT COUNT(DISTINCT po_number) AS count FROM purchase_orders WHERE status = 'pending'"; // Change table/column if needed
$notifResult = $conn->query($notifSql);
if ($notifResult && $notifResult->num_rows > 0) {
    $notifCount = $notifResult->fetch_assoc()['count'];
}

$sql = "
    SELECT p.project_name, pr.particulars, pr.category, pr.amount
    FROM payroll pr
    INNER JOIN projects p ON pr.project_id = p.id
    WHERE pr.status = 'released' $conditionOthers
";

$result = $conn->query($sql);

if (!$result) {
    die("Query error: " . $conn->error);
}

$sqlReimburse = "
    SELECT p.project_name, r.particulars, r.employee_name, r.amount
    FROM reimbursements r
    INNER JOIN projects p ON r.project_id = p.id
    WHERE r.status = 'released' $conditionOthers
";
$resultReimburse = $conn->query($sqlReimburse);

if (!$resultReimburse) {
    die("Query error (reimbursements): " . $conn->error);
}

$reimburseRows = $resultReimburse->fetch_all(MYSQLI_ASSOC);

$sqlMisc = "
    SELECT p.project_name, m.particulars, m.amount, m.supplier_name
    FROM misc_expenses m
    INNER JOIN projects p ON m.project_id = p.id
    WHERE m.status = 'released' $conditionOthers
";
$resultMisc = $conn->query($sqlMisc);

if (!$resultMisc) {
    die("Query error (misc_expenses): " . $conn->error);
}

$miscRows = $resultMisc->fetch_all(MYSQLI_ASSOC);

$sqlOe = "
    SELECT m.particulars, m.amount, m.supplier_name
    FROM office_expenses m
    WHERE m.status = 'released' $conditionOthers
";
$resultOe = $conn->query($sqlOe);

if (!$resultOe) {
    die("Query error (Office_expenses): " . $conn->error);
}

$OeRows = $resultOe->fetch_all(MYSQLI_ASSOC);

$sqlUe = "
    SELECT p.project_name, m.utility_type, m.amount, m.account_number
    FROM utilities_expenses m
    INNER JOIN projects p ON m.project_id = p.id
    WHERE m.status = 'released' $conditionOthers
";
$resultUe = $conn->query($sqlUe);

if (!$resultUe) {
    die("Query error (utilities_expenses): " . $conn->error);
}

$UeRows = $resultUe->fetch_all(MYSQLI_ASSOC);

$sqlIm = "
    SELECT p.project_name, m.particulars, m.amount, m.category
    FROM immediate_material m
    INNER JOIN projects p ON m.project_id = p.id
    WHERE m.status = 'released' $conditionOthers
";
$resultIm = $conn->query($sqlIm);

if (!$resultIm) {
    die("Query error (immediate_material): " . $conn->error);
}

$ImRows = $resultIm->fetch_all(MYSQLI_ASSOC);

$sqlEmergency = "
    SELECT p.project_name, e.particulars, e.amount, e.released_date
    FROM emergency_released e
    INNER JOIN projects p ON e.project_id = p.id
    WHERE e.status = 'released' $conditionOthers
";
$resultEmergency = $conn->query($sqlEmergency);

if (!$resultEmergency) {
    die("Query error (emergency_released): " . $conn->error);
}

$emergencyRows = $resultEmergency->fetch_all(MYSQLI_ASSOC);


$sqlsub = "
    SELECT p.project_name, m.particular, m.tcp, m.category, m.supplier_name
    FROM sub_contracts m
    INNER JOIN projects p ON m.project_id = p.id
    WHERE m.status = 'released' $conditionOthers
";
$resultsub = $conn->query($sqlsub);

if (!$resultsub) {
    die("Query error (sub_contract): " . $conn->error);
}

$subRows = $resultsub->fetch_all(MYSQLI_ASSOC);

$projectsData = [];

// Aggregate all categories
foreach ($result as $row) {
  // Default red
  $color = 'red';

  // If category is Admin or Officer → green
  if (in_array(strtolower($row['category']), ['admin', 'officer'])) {
      $color = 'green';
  }

  $projectsData[$row['project_name']][] = [
      'particulars' => $row['particulars'],
      'actual' => $row['amount'],
      'request' => null,
      'return' => null,
      'release' => $row['amount'],
      'running' => 0,  // placeholder
      'source' => 'payroll',
      'color' => $color  // <-- added
  ];
}


foreach ($reimburseRows as $row) {
    $projectsData[$row['project_name']][] = [
        'particulars' => "REIMBURSEMENT " . $row['employee_name'] . " - " . $row['particulars'],
        'actual' => $row['amount'],
        'request' => null,
        'return' => null,
        'release' => $row['amount'],
        'running' => 0,
        'source' => 'reimbursement'
    ];
}

foreach ($miscRows as $row) {
    $projectsData[$row['project_name']][] = [
        'particulars' => $row['particulars'] . " - " . $row['supplier_name'],
        'actual' => $row['amount'],
        'request' => null,
        'return' => null,
        'release' => $row['amount'],
        'running' => 0,
        'source' => 'misc'
    ];
}

// Office expenses (non-project based)
if (!empty($OeRows)) {
  $projectsData['OE'] = [];
  foreach ($OeRows as $row) {
      $projectsData['OE'][] = [
          'particulars' => $row['particulars'] . " - " . $row['supplier_name'],
          'actual' => $row['amount'],
          'request' => null,
          'return' => null,
          'release' => $row['amount'],
          'running' => 0,
          'source' => 'office_expenses'
      ];
  }
}



foreach ($projects as $projectName => $poList) {
    foreach ($poList as $po_number => $poData) {
        $total = $poData['total'];
        $particulars = $poData['particulars'];
        $projectsData[$projectName][] = [
            'particulars' => $particulars,
            'actual' => $total,
            'request' => null,
            'return' => null,
            'release' => $total,
            'running' => 0,
            'source' => 'project'
        ];
    }
}

foreach ($subRows as $row) {
    $projectsData[$row['project_name']][] = [
        'particulars' => "SUBCONTRACT - {$row['particular']} ({$row['supplier_name']})",
        'actual' => $row['tcp'],
        'request' => null,
        'return' => null,
        'release' => $row['tcp'],
        'running' => 0,
        'source' => 'subcon'
    ];
}

foreach ($UeRows as $row) {
    $projectsData[$row['project_name']][] = [
        'particulars' => "UTILITY - {$row['utility_type']} (Acc #: {$row['account_number']})",
        'actual' => $row['amount'],
        'request' => null,
        'return' => null,
        'release' => $row['amount'],
        'running' => 0,
        'source' => 'utilities'
    ];
}

// Add emergency_released entries
foreach ($emergencyRows as $row) {
  $projectsData[$row['project_name']][] = [
      'particulars' => "EMERGENCY - " . $row['particulars'],
      'actual' => $row['amount'],
      'request' => null,
      'return' => null,
      'release' => $row['amount'],
      'running' => 0,  // placeholder
      'source' => 'emergency'
  ];
}

$fundingPerProject = [];
foreach ($ImRows as $row) {
    $project = $row['project_name'];

    if (strtolower($row['category']) === 'funding') {
        // collect funding separately
        if (!isset($fundingPerProject[$project])) {
            $fundingPerProject[$project] = [
                'request' => 0,
                'release' => 0,
            ];
        }

        $fundingPerProject[$project]['request'] += $row['amount']; 
        $fundingPerProject[$project]['release'] += $row['amount'];
        $fundingPerProject[$project]['particulars'][] = $row['particulars']; 

        // make sure project exists in projectsData para mag-render ang header
        if (!isset($projectsData[$project])) {
            $projectsData[$project] = [];
        }

    } else {
        // only push non-funding immediates
        $projectsData[$project][] = [
            'particulars' => "IMMEDIATE - " . $row['particulars'],
            'actual'      => $row['amount'],
            'request'     => null,
            'return'      => null,
            'release'     => $row['amount'],
            'running'     => 0,
            'source'      => 'immediate'
        ];
    }
}

$runningTotals = []; // Will hold project_name => total

$projectNames = array_keys($projectsData); // Extract project names

foreach ($projectNames as $name) {
    // Get project ID from name
    $stmt = $conn->prepare("SELECT id FROM projects WHERE project_name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->bind_result($project_id);
    $stmt->fetch();
    $stmt->close();

    if (!$project_id) continue;

    // Get combined total from all expense tables
    $stmt = $conn->prepare("
    SELECT (
        COALESCE((SELECT SUM(amount) FROM reimbursements WHERE project_id = ? AND status = 'released'), 0) +
        COALESCE((SELECT SUM(amount) FROM misc_expenses WHERE project_id = ? AND status = 'released'), 0) +
        COALESCE((SELECT SUM(amount) FROM utilities_expenses WHERE project_id = ? AND status = 'released'), 0) +
        COALESCE((SELECT SUM(tcp) FROM sub_contracts WHERE project_id = ? AND status = 'released'), 0) +
        COALESCE((SELECT SUM(amount) FROM payroll WHERE project_id = ? AND status = 'released'), 0) +
        COALESCE((SELECT SUM(amount) FROM emergency_released WHERE project_id = ? AND status = 'released'), 0) +
        COALESCE((SELECT SUM(amount) 
        FROM immediate_material 
        WHERE project_id = ? 
          AND status = 'released' 
          AND category <> 'funding'), 0) +
        COALESCE((SELECT SUM(sa.total_price)
                 FROM released_summary sa
                 JOIN projects p ON sa.ship_project_name = p.project_name
                 WHERE p.id = ?), 0)
    ) AS total
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("iiiiiiii", 
    $project_id, 
    $project_id, 
    $project_id, 
    $project_id, 
    $project_id, 
    $project_id, 
    $project_id, 
    $project_id
);

   $stmt->execute();
    $stmt->bind_result($totalRunning);
    $stmt->fetch();
    $stmt->close();

    $runningTotals[$name] = $totalRunning;
}

$sql = "SELECT COUNT(*) AS pending_count FROM mrf WHERE status = 'Pending'";
$result = $conn->query($sql);
$pending_count = 0;

if ($result && $row = $result->fetch_assoc()) {
    $pending_count = $row['pending_count'];
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Summary Approved | Admin Panel</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Copy your CSS from original */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f7f9fc;
        }
        .navbar {
    background: #1167b1;
    backdrop-filter: blur(8px);
}

.navbar-brand img {
    height: 30px;
}

.nav-link {
    font-weight: 500;
    color: #fff !important;
    position: relative;
    margin-left: 15px;
    margin-right: 15px;
    transition: color 0.3s ease;
}

.nav-link::after {
    content: "";
    display: block;
    height: 2px;
    background-color: #fff;
    width: 0;
    transition: width 0.3s ease;
    position: absolute;
    bottom: -1px;
    left: 0;
}

.nav-link:hover::after {
    width: 100%;
}

        .sidebar {
            position: fixed;
            top: 6%;
            left: 0;
            width: 220px;
            height: 100%;
            background-color: #333;
            color: #fff;
            padding: 20px;
            display: flex;
            flex-direction: column;
            border-right: 3px solid #444;
        }
        .sidebar a {
            color: #fff;
            text-decoration: none;
            padding: 8px 20px;
            margin: 10px 0;
            border-radius: 6px;
            font-weight: 500;
            text-transform: uppercase;
        }
        .sidebar a:hover {
            background-color: #555;
        }
        .btn-logout {
            background-color: #dc3545;
            color: #fff;
            border: none;
            padding: 5px 20px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            width: 100%;
        }
.table th, .table td {
    vertical-align: middle;
    font-size: 14px;
}

    .layout-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: 3rem;
        padding-top: 3rem;
    }

    .main-container {
        flex: 1;
        max-width: 70%; /* prevents overflow on smaller screens */
        background-color: #ffffff;
        padding: 1.5rem;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        margin-left: 18%;
    }

    .main-container h4 {
        font-weight: bold;
        color: #333;
    }

    .main-container table {
        width: 100%;
        border-collapse: collapse;
    }

    .main-container th,
    .main-container td {
        vertical-align: middle;
        font-size: 0.9rem;
        padding: 0.5rem;
    }

    .main-container thead th {
        background-color: #343a40;
        color: white;
    }

    .main-container tbody tr:nth-child {
        background-color: #f9f9f9;
    }

    .side-container {
        width: 300px;
        flex-shrink: 0;
        background-color: #f8f9fa;
        padding: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .side-container h5 {
        font-weight: 600;
        color: #444;
    }

    @media (max-width: 992px) {
        .layout-container {
            flex-direction: column;
        }

        .side-container {
            width: 100%;
        }
    }

    .calendar {
    width: 100%;
    font-size: 12px;
    border-collapse: collapse;
    text-align: center;
  }

  .calendar th {
    background-color: #343a40;
    color: white;
    padding: 5px;
  }

  .calendar td {
    padding: 6px;
    border: 1px solid #dee2e6;
    height: 30px;
    width: 14.2%;
  }

  .calendar .today {
    background-color: green;
    font-weight: bold;
    color: white;
    border: 2px solid #ff9800;
  }

  .highlighted {
  background-color: #ffcc80;
  border-radius: 50%;
  color: black;
  font-weight: bold;
}

.main-header {
  position: relative;
  margin-bottom: 1rem;
}

.download-btn {
  padding: 5px 12px;
  font-size: 14px;
  cursor: pointer;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  margin-left: 91%;
}

.download-btn:hover {
  background-color: #e0e0e0;
  text-decoration: underline;
}

@media print {
  .bg-dark {
    background-color: #cccccc !important;
    color: #000000 !important;
  }
}

.week-batch {
  position: absolute;
  left: 0;              /* stick to the left edge */
  top: 0;               /* align with top of h4 */
  text-align: left;
}

.week-batch .batch-label {
  font-size: 0.8rem;    /* smaller text */
  font-weight: normal;
  color: #555;
  line-height: 1.2;
  margin-top: 2px;      /* tiny gap under week number */
}

.highlight-utilities td:nth-child(-n+3) { color: green; font-weight: bold; }  
.highlight-reimbursement td:nth-child(-n+3) {font-weight: bold; }  
.highlight-misc td:nth-child(-n+3) {font-weight: bold; }  
.highlight-project td:nth-child(-n+3) {font-weight: bold; }  
.highlight-emergency td:nth-child(-n+3) {font-weight: bold; } 
.highlight-funding td:nth-child(-n+3) {color: blue; font-weight: bold; }   
.highlight-immediate td:nth-child(-n+3) {font-weight: bold; }  
.highlight-subcon td:nth-child(-n+3) {font-weight: bold; }  
.highlight-office_expenses td:nth-child(-n+3) {font-weight: bold; }  
    </style>
</head>
<body>


<!-- Sidebar -->
<div class="sidebar">
    <button id="sidebarToggle" class="btn btn-outline-light btn-sm mb-4">
        <i class="fas fa-bars"></i>
    </button>  
    <a href="finance_index.php" style="position: relative; display: inline-block;">
  MRF's
  <span id="mrf-badge" style="
    position: absolute;
    top: -2px;
    right: 80px;
    background: red;
    color: white;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 12px;
    <?= $pending_count > 0 ? 'display:inline-block;' : 'display:none;' ?>
  ">
    <?= $pending_count ?>
  </span>
</a>
    <a href="office_expenses_form.php">Office expenses</a>
    <a href="misc_expenses_form.php">Miscellaneous</a>  
    <a href="reimbursement_form.php">Reimbursement</a>
    <a href="utilities_form.php">Utilities</a>
    <a href="payroll_form.php">Payroll</a>
    <a href="immediate_material.php">Materials</a>
    <a href="sub_contract_form.php">Sub Contract</a>
    <button onclick="window.location.href='finance_logout.php'" class="btn-logout">Logout</button>
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <a class="navbar-brand" href="finance_index.php">
        <img src="/gnlproject/img/logo.png" alt="Logo">
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a class="nav-link" href="released_summary.php">Full Report</a></li>
        <li class="nav-item"><a class="nav-link" href="summary_approved.php">Approved Request</a></li>
            <li class="nav-item"><a class="nav-link" href="summary_declined.php">Declined Request</a></li>
            <li class="nav-item"><a class="nav-link" href="summary_request.php">Requested PO</a></li>
        </ul>
    </div>
</nav>

<div class="layout-container">
    <!-- Main container (table) -->
    <div class="main-container">
    <?php
$displayDate = date('F j, Y', strtotime($selectedDate));

if ($selectedDate) {
    $weekNumber = date('W', strtotime($selectedDate));
    $weekLabel = "Week $weekNumber";
} else {
    $weekLabel = '';
}

// Default batch
$batchLabel = 'Batch 1';

// If `group` is passed via URL, show it
if (isset($_GET['group']) && is_numeric($_GET['group'])) {
    $batchLabel = "Batch " . intval($_GET['group']);
}
?>

<div class="main-header">
  <h4 class="text-center" style="line-height: 1;">
      <span class="week-batch">
          <?= $weekLabel ?>
          <div class="batch-label"><?= $batchLabel ?></div>
      </span>
      GNL RELEASED <?= $displayDate ?>
  </h4>

    <button class="download-btn" onclick="downloadPDF()">Download</button>
</div>

        <table class="table table-bordered table-sm">
            <thead class="thead-dark">
                <tr class="text-center">
                    <th>Particulars</th>
                    <th>Actual Amount</th>
                    <th>Request Amount</th>
                    <th>Return Budget</th>
                    <th>Total Release</th>
                    <th>Total Running Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $totalGNL = 0;
            foreach ($projectsData as $project => $items) {
                echo "<tr><td colspan='6' class='font-weight-bold bg-warning text-dark'>$project</td></tr>";
                $rowspan = count($items);
                $loopIndex = 0;

                $projectTotal = array_sum(array_column($items, 'release'));
                $displayRunning = $project === 'OE' ? $projectTotal : ($runningTotals[$project] ?? $projectTotal);

                foreach ($items as $item) {
                  $totalGNL += $item['release'];
              
                  // decide row class
                  $rowClass = '';
                  if (!empty($item['source'])) {
                      $rowClass = "highlight-{$item['source']}";
                  }
              
                 // pick color if set
$colorStyle = !empty($item['color']) ? "style='color: {$item['color']}; font-weight:bold;'" : '';

echo "<tr class='{$rowClass}'>
    <td {$colorStyle}>{$item['particulars']}</td>               
    <td class='text-right' {$colorStyle}>" . ($item['request'] ? number_format($item['request'], 2) : '') . "</td>
    <td class='text-right' {$colorStyle}>" . number_format($item['actual'], 2) . "</td>
    <td class='text-right' {$colorStyle}>" . ($item['return'] ? number_format($item['return'], 2) : '') . "</td>";

                    if ($loopIndex === 0) {
                        echo "<td class='text-right align-middle' rowspan='{$rowspan}'>" . number_format($projectTotal, 2) . "</td>";
                        echo "<td class='text-right align-middle' rowspan='{$rowspan}'>" . number_format($displayRunning, 2) . "</td>";
                    }

                    echo "</tr>";
                    $loopIndex++;
                }
                if (isset($fundingPerProject[$project])) {
                  $totals = $fundingPerProject[$project];
                  $fundingParts = implode(", ", $totals['particulars']); // join particulars
              
                  echo "<tr class='highlight-funding'>
                          <td>  {$fundingParts}</td>
                          <td class='text-right'></td> <!-- Actual empty -->
                          <td class='text-right'>" . number_format($totals['request'], 2) . "</td>
                          <td class='text-right'></td>
                          <td class='text-right'>" . number_format($totals['release'], 2) . "</td>
                          <td class='text-right'>" . number_format($totals['release'], 2) . "</td>
                        </tr>";
                  $totalGNL += $totals['release'];
              }              
            }
            ?>
            
            <tr class='bg-dark text-white font-weight-bold'>
                <td colspan='4'>TOTAL RELEASE FOR ENCASHMENT</td>
                <td class='text-right'><?= number_format($totalGNL, 2) ?></td>
                <td></td>
            </tr>
            </tbody>
        </table>
    </div>

     <!-- Side container -->
     <div class="side-container">
<h5 class="text-center mb-3">Release History</h5>
<div style="max-width: 250px; margin: 0 auto;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
    <button onclick="changeMonth(-1)">&#8592;</button>
    <strong id="calendar-title"></strong>
    <button onclick="changeMonth(1)">&#8594;</button>
  </div>
  <div id="calendar-container"></div>
  <div id="batch-list" style="margin-top:10px; font-size: 14px;"></div>
</div>
    </div>
    </div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>
<script>
  const weekNumber = "<?= $weekNumber ?>";   // e.g. 33
  const batchNumber = "<?= $batchLabel ?>"; // e.g. Batch 1
</script>
<script>
  let currentYear = new Date().getFullYear();
  let currentMonth = new Date().getMonth();
  let highlightedDates = [];
  const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('year') && urlParams.has('month')) {
  currentYear = parseInt(urlParams.get('year'));
  currentMonth = parseInt(urlParams.get('month'));
}

  function generateCalendar(year, month) {
    const container = document.getElementById('calendar-container');
    container.innerHTML = ''; // Clear previous calendar

    const today = new Date();
    const isCurrentMonth = (year === today.getFullYear() && month === today.getMonth());

    const firstDay = new Date(year, month, 1).getDay();
    const lastDate = new Date(year, month + 1, 0).getDate();

    const monthNames = [
      "January", "February", "March", "April", "May", "June",
      "July", "August", "September", "October", "November", "December"
    ];

    document.getElementById("calendar-title").textContent = `${monthNames[month]} ${year}`;

    const table = document.createElement('table');
    table.className = 'calendar';

    const thead = table.createTHead();
    const daysRow = table.insertRow();
    ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
      const cell = daysRow.insertCell();
      cell.innerHTML = day;
    });

    const tbody = table.createTBody();
    let date = 1;
    for (let i = 0; i < 6; i++) {
      const row = tbody.insertRow();
      for (let j = 0; j < 7; j++) {
        const cell = row.insertCell();
        if (i === 0 && j < firstDay) {
          cell.innerHTML = '';
        } else if (date > lastDate) {
          cell.innerHTML = '';
        } else {
          cell.innerHTML = date;

          const cellDate = new Date(year, month, date);
          const formatted = cellDate.getFullYear() + '-' +
                  String(cellDate.getMonth() + 1).padStart(2, '0') + '-' +
                  String(cellDate.getDate()).padStart(2, '0');

          if (highlightedDates.includes(formatted)) {
            cell.classList.add('highlighted'); // Highlight released dates
            cell.style.cursor = 'pointer';
            cell.addEventListener('click', () => {
              fetchDataForDate(formatted); // <- trigger the new function
            });
          }


          if (isCurrentMonth && date === today.getDate()) {
            cell.classList.add('today');
          }

          date++;
        }
      }
    }

    container.appendChild(table);
  }

  function changeMonth(direction) {
    currentMonth += direction;
    if (currentMonth < 0) {
      currentMonth = 11;
      currentYear--;
    } else if (currentMonth > 11) {
      currentMonth = 0;
      currentYear++;
    }
    generateCalendar(currentYear, currentMonth);
  }

  // Fetch dates from PHP and initialize calendar
  // Fetch dates from PHP and initialize calendar
fetch('fetch_released_pdf.php?_=' + new Date().getTime())
  .then(response => response.json())
  .then(data => {
    console.log('Fetched dates:', data);
    if (Array.isArray(data)) {
      highlightedDates = data;
    } else {
      highlightedDates = [];
      console.error('Expected array, got:', data);
    }

    generateCalendar(currentYear, currentMonth);

    // >>> NEW: show batches for the currently selected date (if any)
    const selectedDateParam = new URLSearchParams(window.location.search).get('date');
    if (selectedDateParam) {
      loadBatchesForDate(selectedDateParam);
    } else {
      const batchList = document.getElementById('batch-list');
      if (batchList) batchList.innerHTML = '<em>Select a highlighted date to see batches.</em>';
    }
    // <<< NEW
  })
  .catch(err => {
    console.error("Failed to load release dates:", err);
    highlightedDates = [];
    generateCalendar(currentYear, currentMonth);

    // Optional: default message when dates fail to load
    const batchList = document.getElementById('batch-list');
    if (batchList) batchList.innerHTML = '<em>Select a highlighted date to see batches.</em>';
  });


    function fetchDataForDate(date) {
  const params = new URLSearchParams(window.location.search);
  params.set('date', date);
  params.set('year', currentYear);
  params.set('month', currentMonth); // JavaScript month (0-based)
  window.location.search = params.toString();
}


async function downloadPDF() {
    const { jsPDF } = window.jspdf;

    // Hide the button
    const downloadBtn = document.querySelector('.download-btn');
    downloadBtn.style.display = 'none';

    const mainContainer = document.querySelector('.main-container');

    // ✅ Add extra margin before rendering
    document.querySelectorAll('h4').forEach(el => {
        el.style.marginBottom = "25px"; // space between title and table
    });

    document.querySelectorAll('table').forEach(el => {
        el.style.marginTop = "30px";
    });

    await new Promise(resolve => setTimeout(resolve, 100));

    const canvas = await html2canvas(mainContainer, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff'
    });

    downloadBtn.style.display = 'inline-block';

    const imgData = canvas.toDataURL('image/png');

    const pdf = new jsPDF({
        orientation: 'portrait',
        unit: 'pt',
        format: [612, 792],
    });

    const pageWidth = 612;
    const pageHeight = 792;

    const ratio = Math.min(pageWidth / canvas.width, pageHeight / canvas.height);
    const imgWidth = canvas.width * ratio;
    const imgHeight = canvas.height * ratio;
    const x = (pageWidth - imgWidth) / 2;
    const y = 20;

    pdf.addImage(imgData, 'PNG', x, y, imgWidth, imgHeight);

    // ✅ Dynamic filename
    let batchNumOnly = batchNumber.replace(/[^0-9]/g, '') || '1'; 
    let fileName = `GNLC_released_week${weekNumber}_batch${batchNumOnly}.pdf`;

    pdf.save(fileName);
}

  function renderBatchList(dateISO, groupNumbers) {
  const container = document.getElementById('batch-list');
  container.innerHTML = '';

  if (!Array.isArray(groupNumbers) || groupNumbers.length === 0) {
    container.innerHTML = '<em>No batches found for this date.</em>';
    return;
  }

  const d = new Date(dateISO);
  const pretty = d.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });

  groupNumbers.forEach(n => {
    const link = document.createElement('a');
    link.href = `?date=${encodeURIComponent(dateISO)}&group=${encodeURIComponent(n)}&year=${currentYear}&month=${currentMonth}`;
    link.textContent = `${pretty} - ${n}`;
    link.style.display = 'block';
    link.style.cursor = 'pointer';
    container.appendChild(link);
  });
}


function loadBatchesForDate(dateISO) {
  const container = document.getElementById('batch-list');
  if (!container) return;

  // Optional: show a loading state
  container.innerHTML = '<em>Loading batches…</em>';

  fetch(`fetch_group.php?date=${encodeURIComponent(dateISO)}`)
    .then(r => r.json())
    .then(groupNumbers => {
      renderBatchList(dateISO, groupNumbers);
    })
    .catch(err => {
      console.error('Error loading group numbers:', err);
      container.innerHTML = '<em>Failed to load batches.</em>';
    });
}

  const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    sidebarToggle.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
    });

    let originalTitle = "Material Requisition Forms | Admin Panel";
let altTitle = "🔔 New MRF Pending!";
let titleToggle = false;
let titleInterval = null;
let isAnimating = false;

function animateTitleStart() {
  if (titleInterval) return; // Already running
  isAnimating = true;

  titleInterval = setInterval(() => {
    document.title = titleToggle ? originalTitle : altTitle;
    titleToggle = !titleToggle;
  }, 1000);
}

function animateTitleStop() {
  clearInterval(titleInterval);
  titleInterval = null;
  isAnimating = false;
  document.title = originalTitle;
}

function updateMRFCount() {
  fetch('get_pending_count.php')
    .then(response => response.text())
    .then(count => {
      const badge = document.getElementById('mrf-badge');
      const num = parseInt(count);

      if (num > 0) {
        badge.textContent = count;
        badge.style.display = 'inline-block';
        if (document.hidden) {
          animateTitleStart(); // Start animation only if tab is hidden
        } else {
          animateTitleStop(); // Stop animation if tab is visible
        }
      } else {
        badge.style.display = 'none';
        animateTitleStop();
      }
    })
    .catch(err => console.error('Error fetching MRF count:', err));
}

// Listen for visibility change
document.addEventListener('visibilitychange', () => {
  if (!document.hidden && isAnimating) {
    // User switched back to tab, stop animation
    animateTitleStop();
  }
});

setInterval(updateMRFCount, 5000);
updateMRFCount(); // Initial call to get started immediately
</script>

</body>
</html>

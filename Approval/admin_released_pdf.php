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

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Summary Approved | Admin Panel</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap & Fonts -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
         
<link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Copy your CSS from original */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f7f9fc;
            margin: 0;
            padding: 0;
        }
         /* Brand colors */
:root {
  --gold: #d4af37;
  --gray-dark: #1e1e1e;
  --gray-light: #2f2f2f;
}

/* Navbar */
.navbar {
  background-color: var(--gray-dark);
}

.navbar-brand .text-gold {
  color: var(--gold);
}

.btn-gold {
  background-color: var(--gold);
  color: #000;
  border: none;
}

.btn-gold:hover {
  background-color: #e1c97a;
  color: #000;
}

/* Sidebar */
.sidebar {
  position: fixed;
  top: 56px; /* Adjusted to sit below navbar */
  left: 0;
  width: 220px;
  height: 100%;
  background-color: var(--gray-light);
  color: #fff;
  padding: 20px;
  display: flex;
  flex-direction: column;
  border-right: 2px solid #444;
  z-index: 1000;
  transition: width 0.3s ease;
}

.sidebar a {
  color: #fff;
  padding: 10px 0;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s;
}

.sidebar a:hover {
  color: var(--gold);
}

.sidebar .btn-dark {
  background-color: #000;
  color: #fff;
  border: none;
  width: 100%;
}

/* Collapsed sidebar */
.sidebar.collapsed {
  width: 60px;
  overflow-x: hidden;
  padding: 10px;
}

.container.content {
  margin-left: 240px;
  transition: margin-left 0.3s ease;
}

.sidebar.collapsed + .container.content {
  margin-left: 60px; /* Matches the collapsed sidebar width */
}


    .sidebar a {
      color: #fff;
      text-decoration: none;
      padding: 8px 20px;
      margin: 10px 0;
      border-radius: 6px;
      transition: background-color 0.3s ease;
      font-weight: 500;
      text-transform: uppercase;
    }
/* Reusable animated underline style */
.navbar-nav .nav-link,
.sidebar a {
  position: relative;
  text-decoration: none;
  color: inherit;
}

.navbar-nav .nav-link::after,
.sidebar a::after {
  content: '';
  position: absolute;
  width: 0%;
  height: 2px;
  bottom: 0;
  left: 0;
  background-color: #d4af37; /* gold */
  transition: width 0.3s ease;
}

.navbar-nav .nav-link:hover::after,
.sidebar a:hover::after {
  width: 100%;
}
        .btn-logout {
            background-color: #dc3545; color: #fff;
            border: none; padding: 5px 20px;
            border-radius: 6px; cursor: pointer;
            font-size: 16px;
            width: 100%; font-weight: bold;
            text-transform: uppercase;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
.badge {
    background-color: red;
    color: white;
    padding: 4px 8px;
    border-radius: 10px;
    font-size: 15px;
    margin-left: 3px;
    position: relative;
    top: -30px; /* Adjust this value as needed */
}

.dropdown-sidebar {
  position: relative;
}

.dropdown-toggle-link {
  display: block;
  padding: 10px;
  color: #fff;
  text-decoration: none;
  cursor: pointer;
}

.dropdown-content {
  display: none;
  flex-direction: column;
  padding-left: 20px;
  margin-top: 5px;
}

.dropdown-content a {
  display: block;
  padding: 5px 0;
  color: #ddd;
  text-decoration: none;
}

.dropdown-content a:hover {
  color: #fff;
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

.dropdown-content {
  display: none;
  flex-direction: column;
  padding-left: 10px;
}

.dropdown-content.show {
  display: flex;
}


/* Tablet and below (≤ 992px) */
@media (max-width: 992px) {
  .container.content {
    margin-left: 0;
  }

  .sidebar.collapsed + .container.content {
    margin-left: 0;
  }

  .layout-container {
    flex-direction: column;
    gap: 10px;
    padding: 1rem;
    margin-top: 1rem;
  }

  .main-container {
    max-width: 100%;
    margin-left: 0;
    padding: 1rem;
  }

  .side-container {
    width: 100%;
    margin-top: 1rem;
  }

  .download-btn {
    margin-left: auto;
    display: block;
    float: right;
  }

  .navbar .navbar-brand {
    font-size: 18px;
  }
}

/* Mobile (≤ 576px) */
@media (max-width: 576px) {
  .btn-logout {
    font-size: 14px;
    padding: 6px 10px;
  }

  .main-container th,
  .main-container td {
    font-size: 0.8rem;
    padding: 0.4rem;
  }

  .calendar td {
    padding: 4px;
    font-size: 11px;
  }

  .download-btn {
    margin-left: 0;
    margin-top: 10px;
    float: none;
    width: 100%;
    text-align: center;
  }
}
/* Mobile layout: stack with sidebar on top */
@media (max-width: 768px) {
  .page-layout {
    flex-direction: column; /* stack vertically */
  }

  .side-container {
    order: 1; /* top */
    width: 100%;
  }

  .main-container {
    order: 2; /* bottom */
    width: 100%;
  }

  /* Keep your existing table responsiveness */
  .main-container table {
    display: block;
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border: 0;
  }

  .main-container table thead {
    font-size: 12px;
  }

  .main-container table th,
  .main-container table td {
    font-size: 11px;
    padding: 6px 8px;
    white-space: nowrap;
  }

  .main-container {
    padding: 1rem 0.5rem;
  }

  .calendar td {
    padding: 4px;
    font-size: 10px;
  }

  .calendar th {
    font-size: 11px;
  }
}
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    top: 59px;
    left: 0;
    width: 100%;
    max-height: 0; /* Initially collapsed */
    overflow-y: hidden;
    overflow-x: auto;
    background-color: var(--gray-light);
    transition: max-height 0.4s ease;
    display: flex;
    flex-direction: column;
    border-bottom: 3px solid #444;
    z-index: 1000;
    margin-top: 35px;
    padding: 35px;
  }

  .sidebar.open {
    max-height: 350px; /* Adjust as needed */
    overflow-y: auto;
  }

  .sidebar a,
  .dropdown-toggle-link {
    display: block;
    padding: 8px 12px;
    font-size: 13px;
    white-space: nowrap;
  }

  .dropdown-content {
    display: none;
    flex-direction: column;
    padding-left: 15px;
    margin-top: 5px;
  }

  .dropdown-sidebar.active .dropdown-content {
    display: flex;
  }
  .layout-container {
    margin-top: 200px;
  }
}

#batch-list {
  margin-top: 10px;
  font-size: 14px;
  font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  background: #f9fafc;
  border: 1px solid #ddd;
  border-radius: 6px;
  padding: 12px;
  cursor: pointer; /* shows hand cursor */
  transition: background 0.2s ease, box-shadow 0.2s ease;
}

#batch-list:hover {
  background: #eef5ff; /* light highlight on hover */
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
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
.highlight-immediate td:nth-child(-n+3) {font-weight: bold; }  
.highlight-funding td:nth-child(-n+3),
.highlight-funding td:nth-child(5),
.highlight-funding td:nth-child(6) {
    color: blue;
    font-weight: bold;
}

.highlight-subcon td:nth-child(-n+3) {font-weight: bold; }  
.highlight-office_expenses td:nth-child(-n+3) {font-weight: bold; }  
    </style>
</head>
<body>


<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="admin_index.php">
      <img src="/gnlproject/img/logo.png" alt="Logo" class="mr-2" style="height: 34px;">
  
 <span class="text-gold font-weight-bold ml-2" style="font-family: 'Raleway', sans-serif; font-weight: 300;">GNL DEVELOPMENT CORPORATION</span>
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ml-auto">

        <li class="nav-item">
          <a class="nav-link text-light" href="#">Support</a>
        </li>
        <li class="nav-item">
        <a href="admin_released_summary.php" class="btn btn-gold ml-lg-3">
  FULL REPORT
</a>

        </li>
      </ul>
    </div>
  </div>
</nav>
<!-- Sidebar -->
<div class="sidebar">
  <button id="sidebarToggle" class="btn btn-sm btn-outline-light mb-3">
    <i class="fas fa-bars"></i>
  </button>

  <a href="admin_index.php">Dashboard</a>
  <a href="add_project.php">Add Project</a>

  <!-- Summary Dropdown -->
  <div class="dropdown-sidebar">
    <a href="#" onclick="toggleSummaryDropdown()" class="dropdown-toggle-link">
      Summaries
      <?php if ($notifCount > 0): ?>
        <span class="badge badge-warning ml-2"><?= $notifCount ?></span>
      <?php endif; ?>
      <i class="fas fa-chevron-down float-right"></i>
    </a>
    <div id="summaryDropdown" class="dropdown-content">
      <a href="admin_summary_request.php">Request <?php if ($notifCount > 0): ?>
        <span class="badge badge-warning ml-2"><?= $notifCount ?></span>
      <?php endif; ?></a>
      <a href="admin_summary_approved.php">Approved</a>      
      <a href="admin_released_pdf.php">Released</a>       
      <a href="admin_summary_declined.php">Declined</a>


    </div>
  </div>

  <a href="admin_sub_contract.php">Add Sub Contracts</a>
  <button onclick="window.location.href='admin_logout.php'" class="btn btn-dark mt-3">Logout</button>
</div>

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
  <div id="batch-list">Select Batch</div>

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
function toggleSummaryDropdown() {
  const dropdown = document.getElementById('summaryDropdown');
  dropdown.classList.toggle('show'); // This adds/removes the 'show' class
}


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
fetch('fetch_released_date.php?_=' + new Date().getTime())
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

  const toggleSidebarBtn = document.querySelector('#sidebarToggle'); // your toggle button
  const sidebar = document.querySelector('.sidebar');

  toggleSidebarBtn?.addEventListener('click', () => {
    sidebar.classList.toggle('open');
  });

  // Dropdown toggle
  document.querySelectorAll('.dropdown-toggle-link').forEach(toggle => {
    toggle.addEventListener('click', function () {
      const parent = this.closest('.dropdown-sidebar');
      parent.classList.toggle('active');
    });
  });


  document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const formData = new FormData(form);

    fetch('submit_feedback.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Feedback Submitted',
                text: data.message
            });
            form.reset();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Submission Failed',
                text: data.message
            });
        }
    })
    .catch(() => {
        Swal.fire({
            icon: 'error',
            title: 'Something went wrong',
            text: 'Please try again later.'
        });
    });
});


//sidebar drop down toggle
function toggleSummaryDropdown() {
    var dropdown = document.getElementById("summaryDropdown");
    dropdown.style.display = (dropdown.style.display === "flex") ? "none" : "flex";
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

  fetch(`fetch_group_number.php?date=${encodeURIComponent(dateISO)}`)
    .then(r => r.json())
    .then(groupNumbers => {
      renderBatchList(dateISO, groupNumbers);
    })
    .catch(err => {
      console.error('Error loading group numbers:', err);
      container.innerHTML = '<em>Failed to load batches.</em>';
    });
}

</script>

</body>
</html>

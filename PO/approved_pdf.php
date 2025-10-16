<?php
session_start();
require_once 'db_connect.php';

// Fetch purchase order entries, ordered by po_number to group easily
$poQuery = "SELECT * FROM summary_approved WHERE status = 'approved' ORDER BY po_number, id";
$poResult = $conn->query($poQuery);

$poGroups = [];

if ($poResult->num_rows > 0) {
    while ($row = $poResult->fetch_assoc()) {
        $po_number = $row['po_number'];
        if (!isset($poGroups[$po_number])) {
            $poGroups[$po_number] = [
                'items' => [],
                'total' => 0,
                'particulars' => $row['particulars'],
            ];
        }

        $poGroups[$po_number]['items'][] = $row;
        $poGroups[$po_number]['total'] += $row['total_price'];
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
$notifSql = "SELECT COUNT(DISTINCT po_number) AS count FROM summary_approved WHERE status = 'approved'"; // Change table/column if needed
$notifResult = $conn->query($notifSql);
if ($notifResult && $notifResult->num_rows > 0) {
    $notifCount = $notifResult->fetch_assoc()['count'];
}

$sql = "
    SELECT p.project_name, pr.particulars, pr.category, pr.amount
    FROM payroll pr
    INNER JOIN projects p ON pr.project_id = p.id
    WHERE pr.status = 'approved' 
";

$result = $conn->query($sql);

if (!$result) {
    die("Query error: " . $conn->error);
}

$sqlReimburse = "
    SELECT p.project_name, r.particulars, r.employee_name, r.amount
    FROM reimbursements r
    INNER JOIN projects p ON r.project_id = p.id
    WHERE r.status = 'approved' 
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
    WHERE m.status = 'approved' 
";
$resultMisc = $conn->query($sqlMisc);

if (!$resultMisc) {
    die("Query error (misc_expenses): " . $conn->error);
}

$miscRows = $resultMisc->fetch_all(MYSQLI_ASSOC);

$sqlOe = "
    SELECT m.particulars, m.amount, m.supplier_name
    FROM office_expenses m
    WHERE m.status = 'approved' 
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
    WHERE m.status = 'approved' 
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
    WHERE m.status = 'approved' 
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
    WHERE m.status = 'approved' 
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
    WHERE e.status = 'approved' 
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
        COALESCE((SELECT SUM(amount) FROM reimbursements WHERE project_id = ? AND status = 'approved'), 0) +
        COALESCE((SELECT SUM(amount) FROM misc_expenses WHERE project_id = ? AND status = 'approved'), 0) +
        COALESCE((SELECT SUM(amount) FROM utilities_expenses WHERE project_id = ? AND status = 'approved'), 0) +
        COALESCE((SELECT SUM(tcp) FROM sub_contracts WHERE project_id = ? AND status = 'approved'), 0) +
        COALESCE((SELECT SUM(amount) FROM payroll WHERE project_id = ? AND status = 'approved'), 0) +
        COALESCE((SELECT SUM(amount) FROM emergency_released WHERE project_id = ? AND status = 'approved'), 0) +
        COALESCE((SELECT SUM(amount) 
          FROM immediate_material 
          WHERE project_id = ? 
            AND status = 'approved' 
            AND category <> 'funding'), 0) +
        COALESCE((SELECT SUM(sa.total_price)
                 FROM summary_approved sa
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
            justify-content: flex-start;
            border-right: 3px solid #444;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            transition: width 0.3s ease;
        }

        .sidebar.collapsed {
            width: 70px;
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

        .sidebar a:hover {
            background-color: #555;
        }

        .btn-logout {
            background-color: #dc3545;
            color: #fff;
            border: none;
            padding: 5px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
            width: 100%;
            font-weight: bold;
            text-transform: uppercase;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s, transform 0.2s ease-in-out;
        }

        .btn-logout:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        .btn-logout:active {
            transform: translateY(0);
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


<!-- Sidebar -->
<div class="sidebar">
    <button id="sidebarToggle" class="btn btn-outline-light btn-sm mb-4">
        <i class="fas fa-bars"></i>
    </button>  
    <a href="finance_index.php" style="position: relative; display: inline-block;">
  MRF's
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
    <a class="navbar-brand d-flex align-items-center" href="finance_index.php">
        <img src="/gnlproject/img/logo.png" alt="Logo" style="height: 40px;">
        <span class="ml-2 font-weight-bold text-uppercase" style="color: gold;">
    GNL Development Corporation
</span>

    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ml-auto">
            <li class="nav-item"><a class="nav-link" href="released_pdf.php">Released Summary</a></li>
            <li class="nav-item"><a class="nav-link" href="summary_approved.php">Approved Request</a></li>
            <li class="nav-item"><a class="nav-link" href="summary_declined.php">Declined Request</a></li>
            <li class="nav-item"><a class="nav-link" href="summary_request.php">Requested PO</a></li>
        </ul>
    </div>
</nav>

<div class="layout-container">
    <!-- Main container (table) -->
    <div class="main-container">
<div class="main-header">
<?php $displayDate = date('F j, Y'); ?>
<h4 class="text-center" style="line-height: 1;">
    GNL APPROVE SUMMARY <?= $displayDate ?>
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
</div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>
<script>
function toggleSummaryDropdown() {
  const dropdown = document.getElementById('summaryDropdown');
  dropdown.classList.toggle('show'); // This adds/removes the 'show' class
}

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

    let fileName = `GNLC_Approved_Summary.pdf`;

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

</script>

</body>
</html>

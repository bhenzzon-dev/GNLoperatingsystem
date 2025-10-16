<?php
session_start();
require_once 'db_connect.php';

// Fetch purchase order entries, ordered by po_number to group easily
$poQuery = "SELECT * FROM released_summary WHERE status = 'approved' ORDER BY po_number, id";
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

$sql = "
    SELECT p.project_name, pr.particulars, pr.category, pr.amount
    FROM payroll pr
    INNER JOIN projects p ON pr.project_id = p.id
    WHERE pr.status = 'released'
";
$result = $conn->query($sql);

if (!$result) {
    die("Query error: " . $conn->error);
}

$sqlReimburse = "
    SELECT p.project_name, r.particulars, r.employee_name, r.amount
    FROM reimbursements r
    INNER JOIN projects p ON r.project_id = p.id
    WHERE r.status = 'released'
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
    WHERE m.status = 'released'
";
$resultMisc = $conn->query($sqlMisc);

if (!$resultMisc) {
    die("Query error (misc_expenses): " . $conn->error);
}

$miscRows = $resultMisc->fetch_all(MYSQLI_ASSOC);

$sqlOe = "
    SELECT p.project_name, m.particulars, m.amount, m.supplier_name
    FROM office_expenses m
    INNER JOIN projects p ON m.project_id = p.id
    WHERE m.status = 'released'
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
    WHERE m.status = 'released'
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
    WHERE m.status = 'released'
";
$resultsub = $conn->query($sqlsub);

if (!$resultsub) {
    die("Query error (sub_contract): " . $conn->error);
}

$subRows = $resultsub->fetch_all(MYSQLI_ASSOC);

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
    <title>Material Requisition Forms | purchasing Panel</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
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
        .container.content {
            margin-left: 240px; padding: 20px;
        }
        .form-container {
            background-color: #fff; border-radius: 10px;
            padding: 30px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 80px;
        }
        .section-header {
            font-weight: 600; font-size: 18px;
            margin-bottom: 10px; color: #333;
            border-bottom: 1px solid #ccc; padding-bottom: 5px;
        }
        table {
            width: 100%; border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
            border: 1px solid #ccc; padding: 10px; text-align: left;
        }
        table th { background-color: #1167b1; color: white; }
.container.content {
    margin-left: 240px; padding: 20px;
}
.form-container {
    background-color: #fff; border-radius: 10px;
    padding: 30px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    margin-top: 80px;
}
.section-header {
    font-weight: 600; font-size: 18px;
    margin-bottom: 10px; color: #333;
    border-bottom: 1px solid #ccc; padding-bottom: 5px;
}
.form-section {
    display: flex; justify-content: space-between; gap: 30px;
    margin-bottom: 30px;
    width: 150%;
}
.form-section .card {
    flex: 1; padding: 20px;
    border-radius: 10px; border: 1px solid #ddd;
    background: #fdfdfd;
}
.po-summary-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 15px;
}

.po-summary-cards > .card {
    flex: 1 1 300px; /* Cards will grow/shrink but try to stay at 300px width */
    padding: 15px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgb(0 0 0 / 0.1);
}

.po-summary-cards .section-header {
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 10px;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
    color: #007bff;
}

.table-responsive {
    flex: 1 1 100%;
    margin-top: 20px;
    width: 150%;
}

.table-responsive table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.table thead.thead-light th {
    background-color: gray;
}

.table thead.thead-dark th {
    background-color: gray;
    color: white;
}

@media print {
    #poContent {
        position: static !important;
        margin: 0 auto !important;
        transform: none !important;
        scale: 1 !important;
        box-shadow: none !important;
    }
}
.pdf-mode {
    position: static !important;
    transform: none !important;
    margin: 0 auto !important;
    scale: 1 !important;
    box-shadow: none !important;
}
.pdf-scale-fit {
    transform: scale(0.8);
    transform-origin: top left;
    width: 100%;
    overflow: hidden;
}
@media print {
    .hide-in-pdf {
        display: none !important;
    }
}
body.generate-pdf .hide-in-pdf {
    display: none !important;
}
.po-info-container {
    position: absolute;
    top: -160px;
    right: 0px;
    font-size: 14px;
    font-family: Arial, sans-serif;
    text-align: left;
}
.po-info-container div {
    margin-bottom: 10px;
}
.po-info-container input {
    width: 180px;
    padding: 5px;
    margin-top: 5px;
    font-size: 14px;
    border-radius: 4px;
    border: 1px solid #ccc;
}
@media print {
    .po-info-container {
        display: none !important;
    }
}
.toggle-po-btn {
  margin-bottom: 15px; /* Adds space below each button */
  display: inline-block; /* ensures margin works properly */
}
.container.content > h3 {
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    font-size: 24px;
    font-weight: 600;
    color: #06402B;
    margin-bottom: 0;
    border-bottom: 2px solid #06402B;
    padding-bottom: 6px;
    max-width: fit-content;
    margin-top: 100px;
  }
  .project-group > h2 {
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    font-size: 26px;
    font-weight: 700;
    color: gray;
    margin-bottom: 20px;
    padding-bottom: 6px;
    border-bottom: 2px solid #2980b9;
    max-width: fit-content;
}
.nav-tabs .nav-link {
    color: black !important;
    background-color: transparent;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.nav-tabs .nav-link.active {
    background-color: gray !important;
    color: white !important;
    font-weight: 600;
}

.nav-tabs .nav-link:hover {
    background-color: #008000 !important;
    color: white !important;
    cursor: pointer;
}
.po-number{
    margin-bottom: 20px;
    font-size: 30px;
}
.minimal-table {
    width: 100%;
    border-collapse: collapse;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #fff;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    border-radius: 12px;
    overflow: hidden;
}

.minimal-table thead {
    background-color: #f4f4f4;
}

.minimal-table th, .minimal-table td {
    padding: 12px 16px;
    text-align: left;
    font-size: 14px;
    border-bottom: 1px solid #eee;
    color: #333;
}

.minimal-table tbody tr:hover {
    background-color: #06402B;
    transition: background-color 0.2s ease;
}

.minimal-table th {
    font-weight: 600;
    color: #444;
}

.minimal-table td button {
    font-size: 13px;
    padding: 6px 12px;
    border-radius: 6px;
}
.selected-row {
  background-color: #ADD8E6!important; /* Light yellow highlight */
}

.status-pending {
    background-color: #fff3cd; /* light yellow */
    color: #856404; /* dark yellow text */
    font-weight: bold;
}

.status-declined {
    background-color: #f8d7da; /* light red */
    color: #721c24; /* dark red text */
    font-weight: bold;
}
.action-btn-form {
    display: inline-block;
    margin-right: 5px;
}
/* Smooth fade in/out for the modal backdrop */
.modal.fade .modal-dialog {
  transition: opacity 0.1s ease, transform 0.1s ease;
  opacity: 0;
  transform: translateY(-25px);
}

.modal.fade.show .modal-dialog {
  opacity: 1;
  transform: translateY(0);
}

/* Optional: also smooth fade for backdrop */
.modal-backdrop.fade {
  opacity: 0;
  transition: opacity 0.1s ease;
}

.modal-backdrop.fade.show {
  opacity: 0.5; /* default backdrop opacity */
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
<?php
$total_release_amount = 0;

// 1. Sum from projects
foreach ($projects as $projectPOs) {
    foreach ($projectPOs as $poData) {
        foreach ($poData['items'] as $item) {
            $total_release_amount += $item['qty'] * $item['unit_price'];
        }
    }
}

// 2. Payroll
$payrollRows = [];
$total_payroll_amount = 0;

if (isset($result) && $result->num_rows > 0) {
    while ($payrollRow = $result->fetch_assoc()) {
        $payrollRows[] = $payrollRow;
        $total_payroll_amount += $payrollRow['amount'];
    }
}
$total_release_amount += $total_payroll_amount;

// 3. Reimbursements — use existing $reimburseRows
$total_reimbursement_amount = 0;
foreach ($reimburseRows as $row) {
    $total_reimbursement_amount += $row['amount'];
}
$total_release_amount += $total_reimbursement_amount;

// 4. misc
$total_misc_amount = 0;
foreach ($miscRows as $row) {
    $total_misc_amount += $row['amount'];
}
$total_release_amount += $total_misc_amount;
// 5. OE
$total_Oe_amount = 0;
foreach ($OeRows as $row) {
    $total_Oe_amount += $row['amount'];
}
$total_release_amount += $total_Oe_amount;

// 6. UE
$total_Ue_amount = 0;
foreach ($UeRows as $row) {
    $total_Ue_amount += $row['amount'];
}
$total_release_amount += $total_Ue_amount;

// 7. subcontract
$total_sub_amount = 0;
foreach ($subRows as $row) {
    $total_sub_amount += $row['tcp'];
}
$total_release_amount += $total_sub_amount;
?>
<div class="container content">
    <h3>Released Summary</h3>
    
    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs" id="projectTabs" role="tablist">
        <?php $i = 0; foreach ($projects as $projectName => $projectPOs): ?>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $i === 0 ? 'active' : '' ?>" 
               id="tab-<?= $i ?>" 
               data-toggle="tab" 
               href="#content-<?= $i ?>" 
               role="tab">
               <?= htmlspecialchars($projectName) ?>
            </a>
        </li>
        <?php $i++; endforeach; ?>
        <li class="nav-item" role="presentation">
    <a class="nav-link" 
       id="tab-payroll" 
       data-toggle="tab" 
       href="#content-payroll" 
       role="tab">
       Payroll Summary
    </a>
</li>
<li class="nav-item" role="presentation">
    <a class="nav-link" 
       id="tab-reimburse" 
       data-toggle="tab" 
       href="#content-reimburse" 
       role="tab">
       Reimbursement
    </a>

<li class="nav-item" role="presentation">
<a class="nav-link" 
   id="tab-misc" 
   data-toggle="tab" 
   href="#content-Misc" 
   role="tab">
   Miscellaneous
</a>

<li class="nav-item" role="presentation">
<a class="nav-link" 
   id="tab-Oe" 
   data-toggle="tab" 
   href="#content-Oe" 
   role="tab">
   Office Expenses
</a>

<li class="nav-item" role="presentation">
<a class="nav-link" 
   id="tab-Ue" 
   data-toggle="tab" 
   href="#content-Ue" 
   role="tab">
   Utility Expenses
</a>

<li class="nav-item" role="presentation">
<a class="nav-link" 
   id="tab-sub" 
   data-toggle="tab" 
   href="#content-sub" 
   role="tab">
   Sub Contract
</a>


    </ul>

   <!-- Tabs Content -->
<div class="tab-content" id="projectTabsContent" style="padding: 20px; background: #f9f9f9; border-radius: 0 0 10px 10px;">

<div class="tab-pane fade" id="content-payroll" role="tabpanel">
    <h4>Payroll Summary</h4>
    <p>This section provides a consolidated overview of employee payroll.</p>

    <table class="table table-bordered">
        <thead class="thead-light">
            <tr>
                <th>Project Name</th>
                <th>Particulars</th>
                <th>Category</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($payrollRows) > 0): ?>
                <?php foreach ($payrollRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['project_name']) ?></td>
                        <td><?= htmlspecialchars($row['particulars']) ?></td>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td>₱<?= number_format($row['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center">No approved payroll entries found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
  </div>

  <div class="tab-pane fade" id="content-reimburse" role="tabpanel">
    <h4>Reimbursement Summary</h4>
    <p>This section provides a summary of approved reimbursements.</p>

    <table class="table table-bordered">
        <thead class="thead-light">
            <tr>
                <th>Project Name</th>
                <th>Particulars</th>
                <th>Category</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($reimburseRows) > 0): ?>
                <?php foreach ($reimburseRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['project_name']) ?></td>
                        <td><?= htmlspecialchars($row['particulars']) ?></td>
                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                        <td>₱<?= number_format($row['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center">No approved reimbursements found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="tab-pane fade" id="content-Misc" role="tabpanel">
    <h4>Miscellaneous Summary</h4>
    <p>This section provides a summary of approved miscellaneous expenses.</p>

    <table class="table table-bordered">
        <thead class="thead-light">
            <tr>
                <th>Project Name</th>
                <th>Particulars</th>
                <th>Supplier Name</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($miscRows) > 0): ?>
                <?php foreach ($miscRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['project_name']) ?></td>
                        <td><?= htmlspecialchars($row['particulars']) ?></td>
                        <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                        <td>₱<?= number_format($row['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center">No approved miscellaneous expenses found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="tab-pane fade" id="content-Oe" role="tabpanel">
    <h4>Office Expenses Summary</h4>
    <p>This section provides a summary of approved office expenses.</p>

    <table class="table table-bordered">
        <thead class="thead-light">
            <tr>
                <th>Project Name</th>
                <th>Particulars</th>
                <th>Supplier Name</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($OeRows) > 0): ?>
                <?php foreach ($OeRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['project_name']) ?></td>
                        <td><?= htmlspecialchars($row['particulars']) ?></td>
                        <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                        <td>₱<?= number_format($row['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center">No approved office expenses found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="tab-pane fade" id="content-Ue" role="tabpanel">
    <h4>Utility Expenses Summary</h4>
    <p>This section provides a summary of approved utility expenses.</p>

    <table class="table table-bordered">
        <thead class="thead-light">
            <tr>
                <th>Project Name</th>
                <th>Utility Type</th>
                <th>Account Number</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($UeRows) > 0): ?>
                <?php foreach ($UeRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['project_name']) ?></td>
                        <td><?= htmlspecialchars($row['utility_type']) ?></td>
                        <td><?= htmlspecialchars($row['account_number']) ?></td>
                        <td>₱<?= number_format($row['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center">No approved utility expenses found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="tab-pane fade" id="content-sub" role="tabpanel">
    <h4>Sub Contract Summary</h4>
    <p>This section provides a summary of approved sub contract expenses.</p>

    <table class="table table-bordered">
        <thead class="thead-light">
            <tr>
                <th>Project Name</th>
                <th>Supplier Name</th>
                <th>Particulars</th>
                <th>Category</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($subRows) > 0): ?>
                <?php foreach ($subRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['project_name']) ?></td>
                        <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                        <td><?= htmlspecialchars($row['particular']) ?></td>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td>₱<?= number_format($row['tcp'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center">No approved utility expenses found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>


<?php 
                    $i = 0;
                    foreach ($projects as $projectName => $projectPOs): 
                    ?>
                        <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="content-<?= $i ?>" role="tabpanel">
                            <div class="summary-request mb-4">
                                <table class="table table-bordered">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Particulars</th>
                                            <th>Amount</th>
                                            <th>PO Number</th>
                                            <th>Status</th>
                                            <th>Purchase Order</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php 
                    $project_total = 0;
                    foreach ($projectPOs as $po_num => $poData): 
                        $date = date('F j, Y', strtotime($poData['items'][0]['created_at']));
                        $vendor = $poData['items'][0];
                        $grand_total = 0;
                        foreach ($poData['items'] as $item) {
                            $grand_total += $item['qty'] * $item['unit_price'];
                        }
                        $project_total += $grand_total;
                    ?>
                                      <tr>
                        <td><?= htmlspecialchars($poData['particulars'] ?? '-') ?></td>
                        <td>₱<?= number_format($grand_total, 2) ?></td>
                        <td><?= htmlspecialchars($po_num) ?></td>
                        <?php 
                            $status = strtolower($poData['items'][0]['status']);
                            $statusClass = "status-" . $status;
                        ?>
                        <td class="<?= htmlspecialchars($statusClass) ?>">
                            <?= htmlspecialchars($poData['items'][0]['status']) ?>
                        </td>
                        <td>
                            <button 
                                class="btn btn-primary toggle-po-btn" 
                                data-target="po-<?= htmlspecialchars($po_num); ?>" 
                                data-po-num="<?= htmlspecialchars($po_num); ?>">
                                Show PO for <?= htmlspecialchars($date); ?>
                            </button>
                        </td>
                    </tr>

                    <tr id="po-<?= htmlspecialchars($po_num); ?>" class="po-container" style="display: none;">
    <td colspan="4" style="padding: 0; border: none;">
        <div style="padding: 15px; border-top: 0px solid #ccc; margin-right: 13px;">

            <!-- START Printable Section -->
            <div id="poContent-<?= htmlspecialchars($po_num); ?>">
            <div id="poInfoHidden" style="display:none;">
                <input type="hidden" id="po_number" value="<?= htmlspecialchars($po_num); ?>">
                <input type="hidden" id="date" value="<?= date('Y-m-d'); ?>">
            </div>

                <div class="form-section">
                    <!-- Vendor Info -->
                    <div class="card mb-3">
                        <div class="section-header">Vendor</div>
                        <?php $vendor = $poData['items'][0]; ?>
                        <p><strong>Supplier Name:</strong> <?= htmlspecialchars($vendor['supplier_name'] ?? 'N/A') ?></p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($vendor['address'] ?? '-') ?></p>
                        <p><strong>Contact Number:</strong> <?= htmlspecialchars($vendor['contact_number'] ?? '-') ?></p>
                        <p><strong>Contact Person:</strong> <?= htmlspecialchars($vendor['contact_person'] ?? '-') ?></p>
                    </div>

                    <!-- Ship To -->
                    <div class="card mb-3">
                        <p class="section-header">Ship To</p>
                        <p><strong>Project Name:</strong> <?= htmlspecialchars($vendor['ship_project_name'] ?? ''); ?></p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($vendor['ship_address'] ?? ''); ?></p>
                        <p><strong>Contact Number:</strong> <?= htmlspecialchars($vendor['ship_contact_number'] ?? ''); ?></p>
                        <p><strong>Contact Person:</strong> <?= htmlspecialchars($vendor['ship_contact_person'] ?? ''); ?></p>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Item ID</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($poData['items'] as $item): 
                            $total = $item['qty'] * $item['unit_price'];
                        ?>
                            <tr data-item-id="<?= $item['id'] ?>">
                                <td><?= $counter++ ?></td>
                                <td><?= htmlspecialchars($item['item_description']) ?></td>
                                <td><?= $item['qty'] ?></td>
                                <td><?= htmlspecialchars($item['unit']) ?></td>
                                <td>₱<?= number_format($item['unit_price'], 2) ?></td>
                                <td>₱<?= number_format($total, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-right font-weight-bold">Grand Total</td>
                                <td>₱<?= number_format($grand_total, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
            <!-- END Printable Section -->

            <button class="btn btn-secondary mt-2" onclick="downloadPDF('poContent-<?= htmlspecialchars($po_num); ?>')">
                Download as PDF
            </button>

        </div>
    </td>
</tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
    <tr>
        <td colspan="5" class="text-right font-weight-bold">
            Grand Total: ₱<?= number_format($project_total, 2) ?>
        </td>
    </tr>
</tfoot>
            </table>
        </div>
    </div>
<?php $i++; endforeach; ?>
</div>
<!-- Bootstrap & jQuery scripts -->
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
        // Sidebar toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');

    sidebarToggle.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
    });
// Toggle button show/hide and update text
document.querySelectorAll('.toggle-po-btn').forEach(button => {
  button.addEventListener('click', () => {
    const targetId = button.getAttribute('data-target');
    const targetDiv = document.getElementById(targetId);
    const showing = targetDiv.style.display === 'block';

    // Close all other open target divs and reset buttons/highlights
    document.querySelectorAll('.toggle-po-btn').forEach(btn => {
      const btnTargetId = btn.getAttribute('data-target');
      const btnTargetDiv = document.getElementById(btnTargetId);

      if (btn !== button) {
        if (btnTargetDiv) btnTargetDiv.style.display = 'none';

        let text = btn.textContent;
        if (text.includes('Hide')) {
          btn.textContent = text.replace('Hide', 'Show');
        }

        const row = btn.closest('tr');
        if (row) row.classList.remove('selected-row');
      }
    });

    // Toggle clicked button/row
    if (showing) {
      targetDiv.style.display = 'none';
      button.textContent = button.textContent.replace('Hide', 'Show');
      const row = button.closest('tr');
      if (row) row.classList.remove('selected-row');
    } else {
      targetDiv.style.display = 'block';
      button.textContent = button.textContent.replace('Show', 'Hide');
      const row = button.closest('tr');
      if (row) row.classList.add('selected-row');

      // ✅ Only fetch po_num here when showing
      const poNum = button.getAttribute('data-po-num');
      console.log("Fetched po_num:", poNum);

      const poInput = document.querySelector('#po_num_input');
      if (poInput) {
        poInput.value = poNum;
      }

      const summaryLink = document.querySelector('#summaryLink');
      if (summaryLink) {
        summaryLink.href = `summary_request.php?po_num=${encodeURIComponent(poNum)}`;
      }
    }
  });
});



$(document).ready(function() {
  // When switching tabs, close all open PO summaries and update buttons
  $('#projectTabs a[data-toggle="tab"]').on('shown.bs.tab', function () {
    $('.toggle-po-btn').each(function() {
      const targetId = $(this).data('target');
      $('#' + targetId).hide();

      let text = $(this).text();
      if (text.includes('Hide')) {
        $(this).text(text.replace('Hide', 'Show'));
      }

      $(this).removeClass('active');

      // Remove highlight from rows when switching tabs
      const row = $(this).closest('tr');
      if (row.length) {
        row.removeClass('selected-row');
      }
    });
  });

  // Optional: On page unload, reset buttons and hide content
  $(window).on('beforeunload', function() {
    $('.toggle-po-btn').each(function() {
      const targetId = $(this).data('target');
      $('#' + targetId).hide();

      let text = $(this).text();
      if (text.includes('Hide')) {
        $(this).text(text.replace('Hide', 'Show'));
      }
      $(this).removeClass('active');

      const row = $(this).closest('tr');
      if (row.length) {
        row.removeClass('selected-row');
      }
    });
  });
});

document.addEventListener('DOMContentLoaded', function () {
    function updateGrandTotal(poNum) {
    // Find the PO container table footer for this PO number
    const poContainer = document.getElementById(`po-${poNum}`);
    if (!poContainer) return;

    // Sum all totals from the item rows inside tbody
    let sum = 0;
    poContainer.querySelectorAll('tbody tr').forEach(row => {
        const totalCell = row.querySelector('td:nth-child(6)');
        if (totalCell) {
            // Extract numeric value from currency formatted text e.g., ₱123.45
            const totalText = totalCell.textContent.trim().replace(/[₱,]/g, '');
            const totalValue = parseFloat(totalText);
            if (!isNaN(totalValue)) sum += totalValue;
        }
    });

    // Update the footer cell that contains the grand total
    const footerTotalCell = poContainer.querySelector('tfoot tr td:last-child');
    if (footerTotalCell) {
        footerTotalCell.textContent = `₱${sum.toFixed(2)}`;
    }
}
});

function toggleSummaryDropdown() {
    var dropdown = document.getElementById("summaryDropdown");
    dropdown.style.display = (dropdown.style.display === "flex") ? "none" : "flex";
  }

  document.getElementById('releaseForm').addEventListener('submit', function (e) {
    e.preventDefault(); // Prevent default form submission

    Swal.fire({
      title: 'Are you sure?',
      text: "This will release all approved items.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#aaa',
      confirmButtonText: 'Yes, release now!'
    }).then((result) => {
      if (result.isConfirmed) {
        e.target.submit(); // Submit the form after confirmation
      }
    });
  });


let originalTitle = "Material Requisition Forms | Purchasing Panel";
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

function downloadPDF(contentId) {
    // Clone the PO content section by ID
    const poContent = document.getElementById(contentId).cloneNode(true);

    // Shrink for PDF
    poContent.style.transform = 'scale(0.85)';
    poContent.style.transformOrigin = 'top left';
    poContent.style.width = '530px';
    poContent.style.marginTop = '140px';

    // Reduce font size for all elements
    poContent.querySelectorAll('*').forEach(el => {
        el.style.fontSize = '12px';
    });

    // Remove any unwanted containers if exist
    const poInfoContainer = poContent.querySelector('.po-info-container');
    if (poInfoContainer) poInfoContainer.remove();

    // Wrapper for positioning all elements
    const wrapper = document.createElement('div');
    wrapper.style.position = 'relative';
    wrapper.appendChild(poContent);

    // Logo
    const logo = document.createElement('img');
    logo.src = '/gnlproject/img/logo.png';
    logo.style.position = 'absolute';
    logo.style.top = '-120px';
    logo.style.left = '0';
    logo.style.width = '120px';
    wrapper.appendChild(logo);

    // Company text
    const textContainer = document.createElement('div');
    textContainer.style.position = 'absolute';
    textContainer.style.top = '-100px';
    textContainer.style.left = '130px';
    textContainer.style.fontSize = '10px';
    textContainer.style.lineHeight = '1.5';
    textContainer.style.fontFamily = 'Arial, sans-serif';
    textContainer.innerHTML = `
        Unit 1007 Civic Prime Plaza, Filinvest Corporate City, Alabang <br>
        Tin # 008-170-572-000 <br>
        Telefaxes No: (02) 8651624
    `;
    wrapper.appendChild(textContainer);

    // Get date & PO number from visible page
    const dateValue = document.getElementById('date')?.value || '';
    const poNumberValue = document.getElementById('po_number')?.value || '';

    // PO info container for PDF header
    const poInfoContainerPDF = document.createElement('div');
    poInfoContainerPDF.style.position = 'absolute';
    poInfoContainerPDF.style.top = '-90px';
    poInfoContainerPDF.style.right = '50px';
    poInfoContainerPDF.style.fontSize = '12px';
    poInfoContainerPDF.style.fontFamily = 'Arial, sans-serif';
    poInfoContainerPDF.innerHTML = `
        <div><strong>Date:</strong> ${dateValue}</div>
        <div><strong>PO Number:</strong> ${poNumberValue}</div>
    `;
    wrapper.appendChild(poInfoContainerPDF);

    // Replace textarea with static text
    poContent.querySelectorAll('textarea').forEach(textarea => {
        const span = document.createElement('span');
        span.textContent = textarea.value || '';
        textarea.replaceWith(span);
    });

    // PDF options
    const opt = {
        margin: [0, 0.5, 0.5, 0.5], // [top, left, bottom, right] in inches
        filename: 'purchase_order.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, scrollY: 0 },
        jsPDF: { unit: 'in', format: 'A4', orientation: 'portrait' }
    };

    // Generate and download PDF
    html2pdf().set(opt).from(wrapper).save();
}
</script>

</body>
</html>

<?php $conn->close(); ?>

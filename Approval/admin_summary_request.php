<?php
session_start();
require_once 'db_connect.php';

// Fetch purchase order entries, ordered by po_number to group easily
$poQuery = "SELECT * FROM purchase_orders ORDER BY po_number, id";
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

// === Handle Approved POs ===
$approvedQuery = "SELECT * FROM purchase_orders WHERE status = 'approved'";
$approvedResult = $conn->query($approvedQuery);

if ($approvedResult && $approvedResult->num_rows > 0) {
    while ($row = $approvedResult->fetch_assoc()) {
        $columns = [
            'po_number', 'item_description', 'qty', 'unit',
            'unit_price', 'total_price', 'supplier_name', 'address',
            'contact_number', 'contact_person', 'ship_project_name', 'ship_address',
            'ship_contact_number', 'ship_contact_person', 'created_at',
            'date', 'particulars', 'po_num', 'status', 'mrf_id'
        ];

        $values = [];
        foreach ($columns as $col) {
            $values[] = "'" . $conn->real_escape_string($row[$col]) . "'";
        }

        $insertQuery = "INSERT INTO summary_approved (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ")";
        if ($conn->query($insertQuery)) {
            // Update requested_mrf status to 'approved' for this mrf_id
            $mrf_id = $conn->real_escape_string($row['mrf_id']);
            $updateRequestedMrf = "UPDATE requested_mrf SET status = 'approved' WHERE id = '$mrf_id'";
            $conn->query($updateRequestedMrf);

            // Delete from purchase_orders after moving
            $deleteQuery = "DELETE FROM purchase_orders WHERE id = " . intval($row['id']);
            $conn->query($deleteQuery);
        } else {
            echo "Failed to insert approved PO ID " . $row['id'] . ": " . $conn->error;
        }
    }
}

// === Handle Declined POs ===
$declinedQuery = "SELECT * FROM purchase_orders WHERE status = 'declined'";
$declinedResult = $conn->query($declinedQuery);

if ($declinedResult && $declinedResult->num_rows > 0) {
    while ($row = $declinedResult->fetch_assoc()) {
        $columns = [
            'po_number', 'item_description', 'qty', 'unit',
            'unit_price', 'total_price', 'supplier_name', 'address',
            'contact_number', 'contact_person', 'ship_project_name', 'ship_address',
            'ship_contact_number', 'ship_contact_person', 'created_at',
            'date', 'particulars', 'po_num', 'status', 'mrf_id'
        ];

        $values = [];
        foreach ($columns as $col) {
            $values[] = "'" . $conn->real_escape_string($row[$col]) . "'";
        }

        $insertQuery = "INSERT INTO summary_declined (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ")";
        if ($conn->query($insertQuery)) {
            // Update requested_mrf status to 'declined' for this mrf_id
            $mrf_id = $conn->real_escape_string($row['mrf_id']);
            $updateRequestedMrf = "UPDATE requested_mrf SET status = 'declined' WHERE id = '$mrf_id'";
            $conn->query($updateRequestedMrf);

            // Delete from purchase_orders after moving
            $deleteQuery = "DELETE FROM purchase_orders WHERE id = " . intval($row['id']);
            $conn->query($deleteQuery);
        } else {
            echo "Failed to insert declined PO ID " . $row['id'] . ": " . $conn->error;
        }
    }
}


// Notification count for pending POs
$notifCount = 0;
$notifSql = "SELECT COUNT(DISTINCT po_number) AS count FROM purchase_orders WHERE status = 'pending'";
$notifResult = $conn->query($notifSql);
if ($notifResult && $notifResult->num_rows > 0) {
    $notifCount = $notifResult->fetch_assoc()['count'];
}

// === Fetch Pending Non-PO Items for Request Tab ===

// Payroll
$sqlPayrollPending = "
    SELECT pr.id, pr.status, p.project_name, pr.particulars, pr.category, pr.amount
    FROM payroll pr
    INNER JOIN projects p ON pr.project_id = p.id
    WHERE pr.status = 'Pending'
";

$resultPayrollPending = $conn->query($sqlPayrollPending);
$pendingPayrollRows = $resultPayrollPending ? $resultPayrollPending->fetch_all(MYSQLI_ASSOC) : [];

// Reimbursements
$sqlReimbursePending = "
    SELECT r.id, r.status, p.project_name, r.particulars, r.employee_name, r.amount
    FROM reimbursements r
    INNER JOIN projects p ON r.project_id = p.id
    WHERE r.status = 'Pending'
";

$resultReimbursePending = $conn->query($sqlReimbursePending);
$pendingReimburseRows = $resultReimbursePending ? $resultReimbursePending->fetch_all(MYSQLI_ASSOC) : [];

// Miscellaneous Expenses
$sqlMiscPending = "
    SELECT m.id, m.status, p.project_name, m.particulars, m.amount, m.supplier_name
    FROM misc_expenses m
    INNER JOIN projects p ON m.project_id = p.id
    WHERE m.status = 'Pending'
";

$resultMiscPending = $conn->query($sqlMiscPending);
$pendingMiscRows = $resultMiscPending ? $resultMiscPending->fetch_all(MYSQLI_ASSOC) : [];

// Office Expenses
$sqlOePending = "
    SELECT oe.id, oe.status, oe.particulars, oe.amount, oe.supplier_name
    FROM office_expenses oe
    WHERE oe.status = 'Pending'
";

$resultOePending = $conn->query($sqlOePending);
$pendingOeRows = $resultOePending ? $resultOePending->fetch_all(MYSQLI_ASSOC) : [];

// Utilities
$sqlUePending = "
    SELECT ue.id, ue.status, p.project_name, ue.utility_type, ue.amount, ue.account_number, ue.billing_period
    FROM utilities_expenses ue
    INNER JOIN projects p ON ue.project_id = p.id
    WHERE ue.status = 'Pending'
";

$resultUePending = $conn->query($sqlUePending);
$pendingUeRows = $resultUePending ? $resultUePending->fetch_all(MYSQLI_ASSOC) : [];

// Sub Contracts
$sqlSubPending = "
    SELECT sc.id, sc.status, p.project_name, sc.particular, sc.tcp, sc.category, sc.supplier_name
    FROM sub_contracts sc
    INNER JOIN projects p ON sc.project_id = p.id
    WHERE sc.status = 'Pending'
";

$resultSubPending = $conn->query($sqlSubPending);
$pendingSubRows = $resultSubPending ? $resultSubPending->fetch_all(MYSQLI_ASSOC) : [];

$sqlImPending = "
    SELECT im.id, im.status, p.project_name, im.particulars, im.amount, im.category
    FROM immediate_material im
    INNER JOIN projects p ON im.project_id = p.id
    WHERE im.status = 'Pending'
";

$resultImPending = $conn->query($sqlImPending);
$pendingImRows = $resultImPending ? $resultImPending->fetch_all(MYSQLI_ASSOC) : [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Summary Request | Admin Panel</title>
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
    background-color: #e9ecef;
}

.table thead.thead-dark th {
    background-color: #343a40;
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
    color: #34495e;
    margin-bottom: 0;
    border-bottom: 2px solid #2980b9;
    padding-bottom: 6px;
    max-width: fit-content;
    margin-top: 100px;
  }
  .project-group > h2 {
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    font-size: 26px;
    font-weight: 700;
    color: #2c3e50;
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
    background-color: #1167b1 !important;
    color: white !important;
    font-weight: 600;
}

.nav-tabs .nav-link:hover {
    background-color: #1167b1 !important;
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
    background-color: #fafafa;
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

.status-approved {
    background-color: #d4edda; /* light green */
    color: #155724; /* dark green text */
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
.nav-link .badge {
    font-size: 0.75rem;
    vertical-align: middle;
    top: 2%;
}

/* Modal Header */
#updateModal .modal-header {
    background-color: #ffc107; /* Bootstrap warning color */
    color: #fff;
    border-bottom: 2px solid #e0a800;
}

#updateModal .modal-title {
    font-weight: 600;
    font-size: 1.25rem;
}

/* Modal Body Inputs */
#updateModal .modal-body .form-label {
    font-weight: 500;
    color: #495057;
}

#updateModal .modal-body .form-control {
    border: 1px solid #ced4da;
    border-radius: 0.35rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

#updateModal .modal-body .form-control:focus {
    border-color: #ffc107;
    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
}

/* Modal Footer */
#updateModal .modal-footer {
    border-top: 2px solid #e0a800;
}

#updateModal .btn-warning {
    background-color: #ffc107;
    color: #212529;
    font-weight: 500;
    transition: background-color 0.2s, color 0.2s;
}

#updateModal .btn-warning:hover {
    background-color: #e0a800;
    color: #fff;
}

#updateModal .btn-secondary {
    background-color: #6c757d;
    color: #fff;
}

#updateModal .btn-secondary:hover {
    background-color: #5a6268;
}

/* Responsive tweaks */
@media (max-width: 576px) {
    #updateModal .modal-dialog {
        max-width: 95%;
        margin: 1.75rem auto;
    }
}

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
          <button class="btn btn-gold ml-lg-3" data-toggle="modal" data-target="#feedbackModal">
            <i class="fas fa-comment-dots"></i> Feedback
          </button>
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

<div class="container content">
    <h3>Summary Request</h3>
    
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
           <?php if (!empty($projectPOs)): ?>
               <span class="badge badge-danger ml-1"><?= count($projectPOs) ?></span>
           <?php endif; ?>
        </a>
    </li>
<?php $i++; endforeach; ?>

        <!-- Additional Tabs for Pending Non-PO Requests -->
<li class="nav-item" role="presentation">
<a class="nav-link" 
   id="tab-payroll" 
   data-toggle="tab" 
   href="#content-payroll" 
   role="tab">
   Payroll Summary
   <?php if (!empty($pendingPayrollRows)): ?>
       <span class="badge badge-danger ml-1"><?= count($pendingPayrollRows) ?></span>
   <?php endif; ?>
</a>
</li>

<li class="nav-item" role="presentation">
<a class="nav-link" 
   id="tab-Im" 
   data-toggle="tab" 
   href="#content-Im" 
   role="tab">
   Immediate Material
   <?php if (!empty($pendingImRows)): ?>
       <span class="badge badge-danger ml-1"><?= count($pendingImRows) ?></span>
   <?php endif; ?>
</a>
</li>

<li class="nav-item" role="presentation">
    <a class="nav-link" 
       id="tab-reimburse" 
       data-toggle="tab" 
       href="#content-reimburse" 
       role="tab">
       Reimbursement
       <?php if (!empty($pendingReimburseRows)): ?>
       <span class="badge badge-danger ml-1"><?= count($pendingReimburseRows) ?></span>
   <?php endif; ?>
    </a>
</li>

<li class="nav-item" role="presentation">
    <a class="nav-link" 
       id="tab-misc" 
       data-toggle="tab" 
       href="#content-Misc" 
       role="tab">
       Miscellaneous
       <?php if (!empty($pendingMiscRows)): ?>
       <span class="badge badge-danger ml-1"><?= count($pendingMiscRows) ?></span>
   <?php endif; ?>
    </a>
</li>

<li class="nav-item" role="presentation">
    <a class="nav-link" 
       id="tab-Oe" 
       data-toggle="tab" 
       href="#content-Oe" 
       role="tab">
       Office Expenses
       <?php if (!empty($pendingOeRows)): ?>
       <span class="badge badge-danger ml-1"><?= count($pendingOeRows) ?></span>
   <?php endif; ?>
    </a>
</li>

<li class="nav-item" role="presentation">
    <a class="nav-link" 
       id="tab-Ue" 
       data-toggle="tab" 
       href="#content-Ue" 
       role="tab">
       Utility Expenses
       <?php if (!empty($pendingUeRows)): ?>
       <span class="badge badge-danger ml-1"><?= count($pendingUeRows) ?></span>
   <?php endif; ?>
    </a>
</li>

<li class="nav-item" role="presentation">
    <a class="nav-link" 
       id="tab-sub" 
       data-toggle="tab" 
       href="#content-sub" 
       role="tab">
       Sub Contract
       <?php if (!empty($pendingSubRows)): ?>
       <span class="badge badge-danger ml-1"><?= count($pendingSubRows) ?></span>
   <?php endif; ?>
    </a>
</li>

    </ul>
    <div class="tab-content" id="projectTabsContent" style="padding: 20px; background: #f9f9f9; border-radius: 0 0 10px 10px;">
    
    <!----immediate material tab----->
    <div class="tab-pane fade" id="content-Im" role="tabpanel">
        <h4>Immediate Materials Summary</h4>
        <p>This section provides a summary of pending Materials requests.</p>
        <?php 
        $totalAmount = 0;
        foreach ($pendingImRows as $row) {
            $totalAmount += $row['amount'];
        }
        ?>
        <div class="alert alert-info" style="font-size: 18px; font-weight: bold;">
        Total Pending Amount: ₱<?= number_format($totalAmount, 2) ?>
    </div>
        <table class="table table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>Project Name</th>
                    <th>Particulars</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totalAmount = 0;
                if (!empty($pendingImRows)): ?>
                    <?php foreach ($pendingImRows as $row): 
                        $totalAmount += $row['amount'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['project_name']) ?></td>
                            <td><?= htmlspecialchars($row['particulars']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td>₱<?= number_format($row['amount'], 2) ?></td>
                            <td>
                            <?php if ($row['status'] === 'pending'): ?>
                                <div style="display: flex; gap: 8px; padding: 4px;">
                            <button class="btn btn-success btn-sm update-status-btn"
                                data-status="approved"
                                data-id="<?= $row['id'] ?>"
                                data-type="immediate_material">
                                Approve
                            </button>
                            <button class="btn btn-danger btn-sm update-status-btn"
                                data-status="declined"
                                data-id="<?= $row['id'] ?>"
                                data-type="immediate_material">
                                Delete
                            </button>
                            <button class="btn btn-warning btn-sm update-btn"
                                    data-id="<?= $row['id'] ?>"
                                    data-type="immediate_material">
                                Update
                            </button>
                                </div>
                            <?php else: ?>
                                <em>No actions available</em>
                            <?php endif; ?>
                        </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center">No pending Material entries found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!-- PAYROLL TAB -->
    <div class="tab-pane fade" id="content-payroll" role="tabpanel">
        <h4>Payroll Summary</h4>
        <p>This section provides a summary of pending payroll requests.</p>
        <?php 
        $totalAmount = 0;
        foreach ($pendingPayrollRows as $row) {
            $totalAmount += $row['amount'];
        }
        ?>
        <div class="alert alert-info" style="font-size: 18px; font-weight: bold;">
        Total Pending Amount: ₱<?= number_format($totalAmount, 2) ?>
    </div>
        <table class="table table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>Project Name</th>
                    <th>Particulars</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pendingPayrollRows)): ?>
                    <?php foreach ($pendingPayrollRows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['project_name']) ?></td>
                            <td><?= htmlspecialchars($row['particulars']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td>₱<?= number_format($row['amount'], 2) ?></td>
                            <td>
                            <?php if ($row['status'] === 'pending'): ?>
                                <div style="display: flex; gap: 8px; padding: 4px;">
                            <button class="btn btn-success btn-sm update-status-btn"
                                data-status="approved"
                                data-id="<?= $row['id'] ?>"
                                data-type="payroll">
                                Approve
                            </button>
                            <button class="btn btn-danger btn-sm update-status-btn"
                                data-status="declined"
                                data-id="<?= $row['id'] ?>"
                                data-type="payroll">
                                Delete
                            </button>
                            <button class="btn btn-warning btn-sm update-btn"
                                    data-id="<?= $row['id'] ?>"
                                    data-type="payroll">
                                Update
                            </button>
                                </div>
                            <?php else: ?>
                                <em>No actions available</em>
                            <?php endif; ?>
                        </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center">No pending payroll entries found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- REIMBURSE TAB -->
    <div class="tab-pane fade" id="content-reimburse" role="tabpanel">
        <h4>Reimbursements</h4>
        <p>This section provides a summary of pending reimbursements.</p>
        <?php 
        $totalAmount = 0;
        foreach ($pendingReimburseRows as $row) {
            $totalAmount += $row['amount'];
        }
        ?>
        <div class="alert alert-info" style="font-size: 18px; font-weight: bold;">
        Total Pending Amount: ₱<?= number_format($totalAmount, 2) ?>
    </div>
        <table class="table table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>Project Name</th>
                    <th>Particulars</th>
                    <th>Employee Name</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pendingReimburseRows)): ?>
                    <?php foreach ($pendingReimburseRows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['project_name']) ?></td>
                            <td><?= htmlspecialchars($row['particulars']) ?></td>
                            <td><?= htmlspecialchars($row['employee_name']) ?></td>
                            <td>₱<?= number_format($row['amount'], 2) ?></td>
                            <td>
                            <?php if ($row['status'] === 'pending'): ?>
                                <div style="display: flex; gap: 8px; padding: 4px;">
                            <button class="btn btn-success btn-sm update-status-btn"
                                data-status="approved"
                                data-id="<?= $row['id'] ?>"
                                data-type="reimbursements">
                                Approve
                            </button>
                            <button class="btn btn-danger btn-sm update-status-btn"
                                data-status="declined"
                                data-id="<?= $row['id'] ?>"
                                data-type="reimbursements">
                                Delete
                            </button>
                            <button class="btn btn-warning btn-sm update-btn"
                                    data-id="<?= $row['id'] ?>"
                                    data-type="reimbursements">
                                Update
                            </button>
                                </div>
                            <?php else: ?>
                                <em>No actions available</em>
                            <?php endif; ?>
                        </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center">No pending reimbursements found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- MISC TAB -->
    <div class="tab-pane fade" id="content-Misc" role="tabpanel">
        <h4>Miscellaneous</h4>
        <p>This section provides a summary of pending miscellaneous expenses.</p>
        <?php 
        $totalAmount = 0;
        foreach ($pendingMiscRows as $row) {
            $totalAmount += $row['amount'];
        }
        ?>
        <div class="alert alert-info" style="font-size: 18px; font-weight: bold;">
        Total Pending Amount: ₱<?= number_format($totalAmount, 2) ?>
    </div>
        <table class="table table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>Project Name</th>
                    <th>Particulars</th>
                    <th>Supplier Name</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pendingMiscRows)): ?>
                    <?php foreach ($pendingMiscRows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['project_name']) ?></td>
                            <td><?= htmlspecialchars($row['particulars']) ?></td>
                            <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                            <td>₱<?= number_format($row['amount'], 2) ?></td>
                            <td>
                            <?php if ($row['status'] === 'pending'): ?>
                                <div style="display: flex; gap: 8px; padding: 4px;">
                            <button class="btn btn-success btn-sm update-status-btn"
                            data-status="approved"
                            data-id="<?= $row['id'] ?>"
                            data-type="misc_expenses">
                            Approve
                            </button>
                            <button class="btn btn-danger btn-sm update-status-btn"
                                data-status="declined"
                                data-id="<?= $row['id'] ?>"
                                data-type="misc_expenses">
                                Delete
                            </button>
                            <button class="btn btn-warning btn-sm update-btn"
                                    data-id="<?= $row['id'] ?>"
                                    data-type="misc_expenses">
                                Update
                            </button>
                                </div>
                            <?php else: ?>
                                <em>No actions available</em>
                            <?php endif; ?>
                        </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center">No pending miscellaneous expenses found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- OFFICE EXPENSES TAB -->
    <div class="tab-pane fade" id="content-Oe" role="tabpanel">
        <h4>Office Expenses</h4>
        <p>This section provides a summary of pending office expenses.</p>
        <?php 
        $totalAmount = 0;
        foreach ($pendingOeRows as $row) {
            $totalAmount += $row['amount'];
        }
        ?>
        <div class="alert alert-info" style="font-size: 18px; font-weight: bold;">
        Total Pending Amount: ₱<?= number_format($totalAmount, 2) ?>
    </div>
        <table class="table table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>Particulars</th>
                    <th>Supplier Name</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pendingOeRows)): ?>
                    <?php foreach ($pendingOeRows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['particulars']) ?></td>
                            <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                            <td>₱<?= number_format($row['amount'], 2) ?></td>
                            <td>
                            <?php if ($row['status'] === 'pending'): ?>
                                <div style="display: flex; gap: 8px; padding: 4px;">
                            <button class="btn btn-success btn-sm update-status-btn"
                            data-status="approved"
                            data-id="<?= $row['id'] ?>"
                            data-type="office_expenses">
                            Approve
                            </button>
                            <button class="btn btn-danger btn-sm update-status-btn"
                                data-status="declined"
                                data-id="<?= $row['id'] ?>"
                                data-type="office_expenses">
                                Delete
                            </button>
                            <button class="btn btn-warning btn-sm update-btn"
                                    data-id="<?= $row['id'] ?>"
                                    data-type="office_expenses">
                                Update
                            </button>
                                </div>
                            <?php else: ?>
                                <em>No actions available</em>
                            <?php endif; ?>
                        </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center">No pending office expenses found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- UTILITY TAB -->
    <div class="tab-pane fade" id="content-Ue" role="tabpanel">
        <h4>Utility Expenses</h4>
        <p>This section provides a summary of pending utility expenses.</p>
        <?php 
        $totalAmount = 0;
        foreach ($pendingUeRows as $row) {
            $totalAmount += $row['amount'];
        }
        ?>
        <div class="alert alert-info" style="font-size: 18px; font-weight: bold;">
        Total Pending Amount: ₱<?= number_format($totalAmount, 2) ?>
    </div>
        <table class="table table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>Project Name</th>
                    <th>Utility Type</th>
                    <th>Billing Period</th>
                    <th>Account Number</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pendingUeRows)): ?>
                    <?php foreach ($pendingUeRows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['project_name']) ?></td>
                            <td><?= htmlspecialchars($row['utility_type']) ?></td>
                            <td><?= htmlspecialchars($row['billing_period']) ?></td>
                            <td><?= htmlspecialchars($row['account_number']) ?></td>
                            <td>₱<?= number_format($row['amount'], 2) ?></td>
                            <td>
                            <?php if ($row['status'] === 'pending'): ?>
                                <div style="display: flex; gap: 8px; padding: 4px;">
                            <button class="btn btn-success btn-sm update-status-btn"
                            data-status="approved"
                            data-id="<?= $row['id'] ?>"
                            data-type="utilities_expenses">
                            Approve
                            </button>
                                <button class="btn btn-danger btn-sm update-status-btn"
                                data-status="declined"
                                data-id="<?= $row['id'] ?>"
                                data-type="utilities_expenses">
                                Delete
                            </button>
                            <button class="btn btn-warning btn-sm update-btn"
                                    data-id="<?= $row['id'] ?>"
                                    data-type="utilities_expenses">
                                Update
                            </button>
                                </div>
                            <?php else: ?>
                                <em>No actions available</em>
                            <?php endif; ?>
                        </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center">No pending utility expenses found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- SUB CONTRACT TAB -->
    <div class="tab-pane fade" id="content-sub" role="tabpanel">
        <h4>Sub Contract</h4>
        <p>This section provides a summary of pending sub-contract expenses.</p>
        <?php 
        $totalAmount = 0;
        foreach ($pendingSubRows as $row) {
            $totalAmount += $row['tcp'];
        }
        ?>
        <div class="alert alert-info" style="font-size: 18px; font-weight: bold;">
        Total Pending Amount: ₱<?= number_format($totalAmount, 2) ?>
    </div>
        <table class="table table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>Project Name</th>
                    <th>Supplier Name</th>
                    <th>Particulars</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pendingSubRows)): ?>
                    <?php foreach ($pendingSubRows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['project_name']) ?></td>
                            <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                            <td><?= htmlspecialchars($row['particular']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td>₱<?= number_format($row['tcp'], 2) ?></td>
                            <td>
                            <?php if ($row['status'] === 'pending'): ?>

                                <div style="display: flex; gap: 8px; padding: 4px;">
                                <button class="btn btn-success btn-sm update-status-btn"
                                data-status="approved"
                                data-id="<?= $row['id'] ?>"
                                data-type="sub_contracts">
                                Approve
                                </button>
                                <button class="btn btn-danger btn-sm update-status-btn"
                                data-status="declined"
                                data-id="<?= $row['id'] ?>"
                                data-type="sub_contracts">
                                Delete
                                </button>
                                <button class="btn btn-warning btn-sm update-btn"
                                    data-id="<?= $row['id'] ?>"
                                    data-type="sub_contracts">
                                Update
                            </button>
                                </div>
                            <?php else: ?>
                                <em>No actions available</em>
                            <?php endif; ?>
                        </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center">No pending sub contract entries found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
<?php 
if (empty($projects)) {
    echo '<p style="text-align:center; font-size: 18px; color: #555;">No purchase order request for today.</p>';
} else {
    $i = 0;
    foreach ($projects as $projectName => $projectPOs): 
?>  
    <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="content-<?= $i ?>" role="tabpanel">
    <div class="summary-request mb-4">
<?php 
    $totalAmount = 0;
    foreach ($projectPOs as $po_num => $poData) {
        $poTotal = 0;
        foreach ($poData['items'] as $item) {
            $poTotal += $item['qty'] * $item['unit_price'];
        }
        $totalAmount += $poTotal;
    }
?>
    <div class="alert alert-info" style="font-size: 18px; font-weight: bold;">
        Total Pending Amount: ₱<?= number_format($totalAmount, 2) ?>
    </div>

            <table class="table table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>Particulars</th>
                        <th>Amount</th>
                        <th>PO Number</th>
                        <th>Status</th>
                        <th>Purchase Order</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($projectPOs as $po_num => $poData): 
                    $date = date('F j, Y', strtotime($poData['items'][0]['created_at']));
                    $vendor = $poData['items'][0];
                    $grand_total = 0;
                    foreach ($poData['items'] as $item) {
                        $grand_total += $item['qty'] * $item['unit_price'];
                    }
                ?>
                    <tr>
                        <td><?= htmlspecialchars($poData['particulars'] ?? '-') ?></td>
                        <td>₱<?= number_format($grand_total, 2) ?></td>
                        <td><?= htmlspecialchars($po_num) ?></td>
                        <?php 
                            $status = strtolower($poData['items'][0]['status']); // Convert status to lowercase
                            $statusClass = "status-" . $status; // e.g., status-pending, status-approved
                        ?>
                        <td class="<?= htmlspecialchars($statusClass) ?>">
                            <?= htmlspecialchars($poData['items'][0]['status']) ?>
                        </td>
                        <td>
                        <button 
                        class="btn btn-primary toggle-po-btn" 
                        data-target="po-<?= htmlspecialchars($po_num); ?>" 
                        data-po-num="<?= htmlspecialchars($po_num); ?>"
                        >
                        Show PO for <?= htmlspecialchars($date); ?>
                        </button>
                </td>
                        <td>
                            <?php if ($status === 'pending'): ?>
                                <div style="display: flex; gap: 8px; padding: 4px;">
                                    <button type="button" class="btn btn-success btn-sm update-status" data-status="approved" data-po="<?= htmlspecialchars($po_num) ?>">Approve</button>
                                    <button type="button" class="btn btn-danger btn-sm update-status" data-status="declined" data-po="<?= htmlspecialchars($po_num) ?>">Decline</button>
                                </div>
                            <?php else: ?>
                                <em>No actions available</em>
                            <?php endif; ?>
                        </td>


                    </tr>

                    <!-- Hidden summary section -->
                    <tr id="po-<?= htmlspecialchars($po_num); ?>" class="po-container" style="display: none;">
                     <td colspan="4" style="padding: 0; border: none;">
                        <div style="padding: 15px; border-top: 0px solid #ccc; margin-right: 13px;">
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
                                                <th>Action</th> <!-- New column -->
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php 
                                        $counter = 1;
                                        foreach ($poData['items'] as $item): 
                                            $total = $item['qty'] * $item['unit_price'];
                                        ?>
                                            <tr data-item-id="<?= $item['id'] ?>"> <!-- Optional: useful for JS -->
                                                <td><?= $counter++ ?></td>
                                                <td><?= htmlspecialchars($item['item_description']) ?></td>
                                                <td><?= $item['qty'] ?></td>
                                                <td><?= htmlspecialchars($item['unit']) ?></td>
                                                <td>₱<?= number_format($item['unit_price'], 2) ?></td>
                                                <td>₱<?= number_format($total, 2) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning edit-btn"
                                                        data-id="<?= $item['id'] ?>"
                                                        data-description="<?= htmlspecialchars($item['item_description']) ?>"
                                                        data-qty="<?= $item['qty'] ?>"
                                                        data-unit="<?= $item['unit'] ?>"
                                                        data-price="<?= $item['unit_price'] ?>">
                                                        Edit
                                                    </button>

                                                    <button class="btn btn-sm btn-danger delete-btn"
                                                        data-id="<?= $item['id'] ?>">
                                                        Delete
                                                    </button>
                                                </td>

                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="6" class="text-right font-weight-bold">Grand Total</td>
                                                <td>₱<?= number_format($grand_total, 2) ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div>
                                <button class="btn btn-success addItemBtn" 
                                        data-po_num="<?= htmlspecialchars($po_num); ?>">
                                    + Add Item
                                </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php $i++; endforeach; }?>
</div>

<div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-labelledby="addItemModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="addItemForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add PO Item</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
        <input type="hidden" name="po_num" id="modalPoNum">
        <input type="hidden" name="supplier_name" id="supplierName">
        <input type="hidden" name="address" id="address">
        <input type="hidden" name="contact_number" id="contactNumber">
        <input type="hidden" name="contact_person" id="contactPerson">
        <input type="hidden" name="ship_project_name" id="shipProjectName">
        <input type="hidden" name="ship_address" id="shipAddress">
        <input type="hidden" name="ship_contact_number" id="shipContactNumber">
        <input type="hidden" name="ship_contact_person" id="shipContactPerson">
        <input type="hidden" name="particulars" id="particulars">
        <input type="hidden" name="date" id="poDate">

          <div class="form-group">
            <label>Item Description</label>
            <input type="text" class="form-control" name="item_description" required>
          </div>

          <div class="form-group">
            <label>Quantity</label>
            <input type="number" class="form-control" name="qty" id="qty" required>
          </div>

          <div class="form-group">
            <label>Unit</label>
            <input type="text" class="form-control" name="unit" required>
          </div>

          <div class="form-group">
            <label>Unit Price</label>
            <input type="number" class="form-control" name="unit_price" id="unit_price" required>
          </div>

          <div class="form-group">
            <label>Total Price</label>
            <input type="text" class="form-control" name="total_price" id="total_price" readonly>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Add</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ✅ UNIVERSAL UPDATE MODAL -->
<div class="modal fade" id="updateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      
      <div class="modal-header bg-warning text-white">
        <h5 class="modal-title">Update Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="recordId">
        <input type="hidden" id="recordType">

        <!-- ===== IMMEDIATE MATERIAL ===== -->
        <div class="type-field" id="field-immediate_material">
          <div class="mb-3">
            <label>Project Name</label>
            <input type="text" class="form-control" id="im-project_name">
          </div>
          <div class="mb-3">
            <label>Particulars</label>
            <input type="text" class="form-control" id="im-particulars">
          </div>
          <div class="mb-3">
            <label>Category</label>
            <input type="text" class="form-control" id="im-category">
          </div>
          <div class="mb-3">
            <label>Amount</label>
            <input type="number" class="form-control" id="im-amount">
          </div>
        </div>

        <!-- ===== PAYROLL ===== -->
        <div class="type-field" id="field-payroll">
          <div class="mb-3">
            <label>Project Name</label>
            <input type="text" class="form-control" id="payroll-project_name">
          </div>
          <div class="mb-3">
            <label>Particulars</label>
            <input type="text" class="form-control" id="payroll-particulars">
          </div>
          <div class="mb-3">
            <label>Category</label>
            <input type="text" class="form-control" id="payroll-category">
          </div>
          <div class="mb-3">
            <label>Amount</label>
            <input type="number" class="form-control" id="payroll-amount">
          </div>
        </div>

        <!-- ===== REIMBURSEMENTS ===== -->
        <div class="type-field" id="field-reimbursements">
          <div class="mb-3">
            <label>Project Name</label>
            <input type="text" class="form-control" id="reim-project_name">
          </div>
          <div class="mb-3">
            <label>Particulars</label>
            <input type="text" class="form-control" id="reim-particulars">
          </div>
          <div class="mb-3">
            <label>Employee Name</label>
            <input type="text" class="form-control" id="reim-employee_name">
          </div>
          <div class="mb-3">
            <label>Amount</label>
            <input type="number" class="form-control" id="reim-amount">
          </div>
        </div>

        <!-- ===== MISC EXPENSES ===== -->
        <div class="type-field" id="field-misc_expenses">
          <div class="mb-3">
            <label>Project Name</label>
            <input type="text" class="form-control" id="misc-project_name">
          </div>
          <div class="mb-3">
            <label>Particulars</label>
            <input type="text" class="form-control" id="misc-particulars">
          </div>
          <div class="mb-3">
            <label>Supplier Name</label>
            <input type="text" class="form-control" id="misc-supplier_name">
          </div>
          <div class="mb-3">
            <label>Amount</label>
            <input type="number" class="form-control" id="misc-amount">
          </div>
        </div>

        <!-- ===== OFFICE EXPENSES ===== -->
        <div class="type-field" id="field-office_expenses">
          <div class="mb-3">
            <label>Particulars</label>
            <input type="text" class="form-control" id="oe-particulars">
          </div>
          <div class="mb-3">
            <label>Supplier Name</label>
            <input type="text" class="form-control" id="oe-supplier_name">
          </div>
          <div class="mb-3">
            <label>Amount</label>
            <input type="number" class="form-control" id="oe-amount">
          </div>
        </div>

        <!-- ===== UTILITIES EXPENSES ===== -->
        <div class="type-field" id="field-utilities_expenses">
          <div class="mb-3">
            <label>Project Name</label>
            <input type="text" class="form-control" id="ue-project_name">
          </div>
          <div class="mb-3">
            <label>Utility Type</label>
            <input type="text" class="form-control" id="ue-utility_type">
          </div>
          <div class="mb-3">
            <label>Billing Period</label>
            <input type="text" class="form-control" id="ue-billing_period">
          </div>
          <div class="mb-3">
            <label>Account Number</label>
            <input type="text" class="form-control" id="ue-account_number">
          </div>
          <div class="mb-3">
            <label>Amount</label>
            <input type="number" class="form-control" id="ue-amount">
          </div>
        </div>

        <!-- ===== SUB CONTRACTS ===== -->
        <div class="type-field" id="field-sub_contracts">
          <div class="mb-3">
            <label>Project Name</label>
            <input type="text" class="form-control" id="sub-project_name">
          </div>
          <div class="mb-3">
            <label>Supplier Name</label>
            <input type="text" class="form-control" id="sub-supplier_name">
          </div>
          <div class="mb-3">
            <label>Particulars</label>
            <input type="text" class="form-control" id="sub-particular">
          </div>
          <div class="mb-3">
            <label>Category</label>
            <input type="text" class="form-control" id="sub-category">
          </div>
          <div class="mb-3">
            <label>Amount (TCP)</label>
            <input type="number" class="form-control" id="sub-tcp">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" id="saveChangesBtn" class="btn btn-warning">Save Changes</button>
      </div>

    </div>
  </div>
</div>




<!-- Bootstrap & jQuery scripts -->
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        summaryLink.href = `admin_summary_request.php?po_num=${encodeURIComponent(poNum)}`;
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

document.querySelectorAll('.update-status').forEach(button => {
    button.addEventListener('click', function () {
        const po_num = this.dataset.po;
        const status = this.dataset.status;

        Swal.fire({
            icon: 'warning',
            title: `Are you sure you want to ${status} this PO?`,
            text: "There's no going back after doing it.",
            showCancelButton: true,
            confirmButtonText: `Yes, ${status} it`,
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('update_summary_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `po_num=${encodeURIComponent(po_num)}&status=${encodeURIComponent(status)}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data === 'success') { // your PHP should echo "success" on successful move
                        Swal.fire({
                            icon: 'success',
                            title: `PO ${status.charAt(0).toUpperCase() + status.slice(1)}`,
                            text: `The PO has been successfully ${status}.`,
                            timer: 1500,
                            showConfirmButton: false,
                            willClose: () => {
                            }
                        });

                        // Remove the row from DOM in real-time
                        const row = button.closest('tr');
                        if (row) row.remove();

                    } else {
                        Swal.fire('Error', data || 'Something went wrong.', 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Network error occurred.', 'error');
                });
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

    // DELETE
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.dataset.id;
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete the item.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('update_po_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete&id=${encodeURIComponent(id)}`
                    })
                    .then(res => res.text())
                    .then(() => {
    Swal.fire('Deleted!', 'The item has been removed.', 'success').then(() => {
        // Remove the deleted row from the table immediately
        const row = button.closest('tr');
        if (row) {
            const poNum = row.closest('.po-container').id.replace('po-', '');
            row.remove();
            updateGrandTotal(poNum); // update grand total for this PO
        }
    });
});

                }
            });
        });
    });


 // EDIT
document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', () => {
        const id = button.dataset.id;
        const description = button.dataset.description;
        const qty = button.dataset.qty;
        const unit = button.dataset.unit;
        const price = button.dataset.price;

        Swal.fire({
  title: 'Edit Item',
  html:
    `<input id="editDesc" class="swal2-input" placeholder="Description" value="${description}">` +
    `<input id="editQty" type="number" class="swal2-input" placeholder="Qty" value="${qty}">` +
    `<input id="editUnit" class="swal2-input" placeholder="Unit" value="${unit}">` +
    `<input id="editPrice" type="number" step="0.01" class="swal2-input" placeholder="Unit Price" value="${price}">`,
  focusConfirm: false,
  preConfirm: () => {
    let qtyValue = document.getElementById('editQty').value.trim();
    const qtyNumber = qtyValue === '' || isNaN(qtyValue) ? 0 : parseInt(qtyValue, 10);

    return {
      id,
      description: document.getElementById('editDesc').value.trim(),
      qty: qtyNumber,
      unit: document.getElementById('editUnit').value.trim(),
      price: document.getElementById('editPrice').value.trim()
    };
  }


        }).then(result => {
            if (result.isConfirmed) {
                const data = result.value;
                const body = `action=edit&id=${data.id}&item_description=${encodeURIComponent(data.description)}&qty=${data.qty}&unit=${encodeURIComponent(data.unit)}&unit_price=${data.price}`;


                console.log("Data to send:", data); // Moved before fetch to ensure correct logging

                fetch('update_po_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                })
                .then(res => res.text())
                .then(responseText => {
                    console.log("Update response:", responseText);
                    const row = document.querySelector(`tr[data-item-id="${data.id}"]`);
                    if (!row) return console.warn("Row not found for ID:", data.id);

                    row.children[1].textContent = data.description;
                    row.children[2].textContent = data.qty;
                    row.children[3].textContent = data.unit;
                    row.children[4].textContent = `₱${parseFloat(data.price).toFixed(2)}`;

                    const total = parseFloat(data.qty) * parseFloat(data.price);
                    row.children[5].textContent = `₱${total.toFixed(2)}`;

                    const editBtn = row.querySelector('.edit-btn');
                    if (editBtn) {
                        editBtn.setAttribute('data-description', data.description);
                        editBtn.setAttribute('data-qty', data.qty);
                        editBtn.setAttribute('data-unit', data.unit);
                        editBtn.setAttribute('data-price', data.price);
                    }

                    const poContainer = row.closest('[id^="po-"]');
                    if (poContainer) {
                        const poNum = poContainer.id.replace('po-', '');
                        updateGrandTotal(poNum);
                    }

                    Swal.fire('Updated!', 'The item has been updated.', 'success');
                });
            }
        });
    });
});


});

document.addEventListener('DOMContentLoaded', function () {
  const addItemBtns = document.querySelectorAll('.addItemBtn');

  addItemBtns.forEach(button => {
    button.addEventListener('click', function () {
      const poNum = this.getAttribute('data-po_num');
      console.log('Fetched PO Number:', poNum);
      document.getElementById('modalPoNum').value = poNum;

      // Find the hidden PO summary row by ID (#po-PO_NUM)
      const poRow = document.getElementById(`po-${poNum}`);

if (poRow) {
  // Vendor info
  let supplierName = '', address = '', contactNumber = '', contactPerson = '';
  const vendorCard = poRow.querySelector('.card:nth-of-type(1)');
  if (vendorCard) {
    vendorCard.querySelectorAll('p').forEach(p => {
      const text = p.textContent;
      if (text.includes('Supplier Name:')) supplierName = text.replace('Supplier Name:', '').trim();
      else if (text.includes('Address:')) address = text.replace('Address:', '').trim();
      else if (text.includes('Contact Number:')) contactNumber = text.replace('Contact Number:', '').trim();
      else if (text.includes('Contact Person:')) contactPerson = text.replace('Contact Person:', '').trim();
    });
  }

  // Ship To info
  let shipProjectName = '', shipAddress = '', shipContactNumber = '', shipContactPerson = '';
  const shipCard = poRow.querySelector('.card:nth-of-type(2)');
  if (shipCard) {
    shipCard.querySelectorAll('p').forEach(p => {
      const text = p.textContent;
      if (text.includes('Project Name:')) shipProjectName = text.replace('Project Name:', '').trim();
      else if (text.includes('Address:')) shipAddress = text.replace('Address:', '').trim();
      else if (text.includes('Contact Number:')) shipContactNumber = text.replace('Contact Number:', '').trim();
      else if (text.includes('Contact Person:')) shipContactPerson = text.replace('Contact Person:', '').trim();
    });
  }

  // Particulars and Date from main row (previous sibling)
  const mainRow = poRow.previousElementSibling;
  let particulars = '';
  let date = '';
  if (mainRow) {
    particulars = mainRow.querySelector('td:first-child')?.textContent.trim() || '';
    date = mainRow.querySelector('td:nth-child(3)')?.textContent.trim() || '';
  }

  // Set hidden inputs
  document.getElementById('supplierName').value = supplierName;
  document.getElementById('address').value = address;
  document.getElementById('contactNumber').value = contactNumber;
  document.getElementById('contactPerson').value = contactPerson;
  document.getElementById('shipProjectName').value = shipProjectName;
  document.getElementById('shipAddress').value = shipAddress;
  document.getElementById('shipContactNumber').value = shipContactNumber;
  document.getElementById('shipContactPerson').value = shipContactPerson;
  document.getElementById('particulars').value = particulars;
  document.getElementById('poDate').value = date;
}


      // Reset modal form fields except hidden inputs we just set
      const addItemForm = document.getElementById('addItemForm');
      // Save hidden fields
      const hiddenValues = {
        supplierName: document.getElementById('supplierName').value,
        address: document.getElementById('address').value,
        contactNumber: document.getElementById('contactNumber').value,
        contactPerson: document.getElementById('contactPerson').value,
        shipProjectName: document.getElementById('shipProjectName').value,
        shipAddress: document.getElementById('shipAddress').value,
        shipContactNumber: document.getElementById('shipContactNumber').value,
        shipContactPerson: document.getElementById('shipContactPerson').value,
        particulars: document.getElementById('particulars').value,
        poDate: document.getElementById('poDate').value,
        modalPoNum: document.getElementById('modalPoNum').value
      };

      addItemForm.reset();

      // Restore hidden inputs after reset
      for (const [key, val] of Object.entries(hiddenValues)) {
        document.getElementById(key).value = val;
      }

      document.getElementById('total_price').value = '';

      // Show modal
      const modal = new bootstrap.Modal(document.getElementById('addItemModal'));
      modal.show();
    });
  });

  // Auto-calculate total price
  const qtyInput = document.getElementById('qty');
  const unitPriceInput = document.getElementById('unit_price');
  const totalPriceInput = document.getElementById('total_price');

  function calculateTotal() {
    const qty = parseFloat(qtyInput.value) || 0;
    const unitPrice = parseFloat(unitPriceInput.value) || 0;
    totalPriceInput.value = (qty * unitPrice).toFixed(2);
  }

  qtyInput.addEventListener('input', calculateTotal);
  unitPriceInput.addEventListener('input', calculateTotal);

  // Handle form submission
  // Handle form submission
  document.getElementById('addItemForm').addEventListener('submit', function (e) {
  e.preventDefault();

  const form = this;  // store reference for later removal
  const formData = new FormData(form);

  fetch('insert_po_item.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.text())
  .then(data => {
    console.log('Response:', data);

    if (data === 'success') {
      Swal.fire({
  icon: 'success',
  title: 'Success',
  text: 'PO Item added successfully!',
  timer: 2000,
  showConfirmButton: false
}).then(() => {
  const modalEl = document.getElementById('addItemModal');
  if (window.jQuery && $(modalEl).data('bs.modal')) {
    // Bootstrap 4: get modal instance and hide
    const modalInstance = $(modalEl).data('bs.modal');
    modalEl.addEventListener('hidden.bs.modal', () => {
      form.remove();
    }, { once: true });
    modalInstance.hide();
  } else if (window.jQuery) {
    // fallback jQuery hide
    $(modalEl).on('hidden.bs.modal', function () {
      form.remove();
    });
    $(modalEl).modal('hide');
  } else {
    console.warn('Bootstrap modal instance or jQuery not found.');
    form.remove();
  }
});

    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Error: ' + data,
      });
    }
  })
  .catch(err => {
    console.error('AJAX Error:', err);
    Swal.fire({
      icon: 'error',
      title: 'Oops...',
      text: 'An error occurred. Check console for details.',
    });
  });
});
});

function toggleSummaryDropdown() {
    var dropdown = document.getElementById("summaryDropdown");
    dropdown.style.display = (dropdown.style.display === "flex") ? "none" : "flex";
  }


  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.update-status-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const status = this.dataset.status;
            const type = this.dataset.type;

            Swal.fire({
                title: `Are you sure?`,
                text: `You are about to ${status} this request.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, do it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('update_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `id=${id}&status=${status}&type=${type}`
                    })
                    .then(res => res.json()) // expecting JSON
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Status updated successfully.',
                                icon: 'success'
                            }).then(() => {
                                // ✅ Save scroll position
                                localStorage.setItem("scrollPos", window.scrollY);

                                // Save active tab before reload
                            const activeTab = document.querySelector('.nav-tabs .nav-link.active');
                            if (activeTab) {
                                localStorage.setItem('activeTab', activeTab.getAttribute('href'));
                            }


                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Failed to update.', 'error');
                        }
                    })
                    .catch(() => {
                        Swal.fire('Error', 'Something went wrong with the request.', 'error');
                    });
                }
            });
        });
    });
});

// Restore active tab on load
window.addEventListener("load", () => {
    const activeTab = localStorage.getItem("activeTab");
    if (activeTab) {
        const tabEl = document.querySelector(`a[href="${activeTab}"]`);
        if (tabEl) {
            // Bootstrap 5
            if (typeof bootstrap !== "undefined" && bootstrap.Tab) {
                new bootstrap.Tab(tabEl).show();
            } 
            // Bootstrap 4 (fallback with jQuery)
            else if (window.jQuery) {
                $(tabEl).tab('show');
            }
        }
        localStorage.removeItem("activeTab");
    }

    // restore scroll position
    const scrollPos = localStorage.getItem("scrollPos");
    if (scrollPos) {
        window.scrollTo(0, scrollPos);
        localStorage.removeItem("scrollPos");
    }
});

document.addEventListener('DOMContentLoaded', () => {
  const updateModal = new bootstrap.Modal(document.getElementById('updateModal'));
  const typeFields = document.querySelectorAll('.type-field');
  const saveBtn = document.getElementById('saveChangesBtn');

  // Handle click on any "Update" button
  document.querySelectorAll('.update-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const type = btn.dataset.type;

      // Hide all field groups
      typeFields.forEach(f => f.style.display = 'none');

      // Show only the relevant one
      const fieldGroup = document.getElementById('field-' + type);
      if (fieldGroup) fieldGroup.style.display = 'block';

      // Save info in hidden inputs
      document.getElementById('recordId').value = id;
      document.getElementById('recordType').value = type;

      // Fetch existing data
      fetch(`update_record.php?id=${id}&type=${type}`)
        .then(res => res.json())
        .then(data => fillModalFields(data, type))
        .catch(err => console.error('Fetch error:', err));

      updateModal.show();
    });
  });

  // Fill modal fields
  function fillModalFields(data, type) {
    switch (type) {
      case 'immediate_material':
        document.getElementById('im-project_name').value = data.project_name || '';
        document.getElementById('im-particulars').value = data.particulars || '';
        document.getElementById('im-category').value = data.category || '';
        document.getElementById('im-amount').value = data.amount || '';
        break;
      case 'payroll':
        document.getElementById('payroll-project_name').value = data.project_name || '';
        document.getElementById('payroll-particulars').value = data.particulars || '';
        document.getElementById('payroll-category').value = data.category || '';
        document.getElementById('payroll-amount').value = data.amount || '';
        break;
      case 'reimbursements':
        document.getElementById('reim-project_name').value = data.project_name || '';
        document.getElementById('reim-particulars').value = data.particulars || '';
        document.getElementById('reim-employee_name').value = data.employee_name || '';
        document.getElementById('reim-amount').value = data.amount || '';
        break;
      case 'misc_expenses':
        document.getElementById('misc-project_name').value = data.project_name || '';
        document.getElementById('misc-particulars').value = data.particulars || '';
        document.getElementById('misc-supplier_name').value = data.supplier_name || '';
        document.getElementById('misc-amount').value = data.amount || '';
        break;
      case 'office_expenses':
        document.getElementById('oe-particulars').value = data.particulars || '';
        document.getElementById('oe-supplier_name').value = data.supplier_name || '';
        document.getElementById('oe-amount').value = data.amount || '';
        break;
      case 'utilities_expenses':
        document.getElementById('ue-project_name').value = data.project_name || '';
        document.getElementById('ue-utility_type').value = data.utility_type || '';
        document.getElementById('ue-billing_period').value = data.billing_period || '';
        document.getElementById('ue-account_number').value = data.account_number || '';
        document.getElementById('ue-amount').value = data.amount || '';
        break;
      case 'sub_contracts':
        document.getElementById('sub-project_name').value = data.project_name || '';
        document.getElementById('sub-supplier_name').value = data.supplier_name || '';
        document.getElementById('sub-particular').value = data.particular || '';
        document.getElementById('sub-category').value = data.category || '';
        document.getElementById('sub-tcp').value = data.tcp || '';
        break;
    }
  }

  // Save updates (with Swal confirmation)
  saveBtn.addEventListener('click', () => {
    Swal.fire({
      title: 'Confirm Update',
      text: 'Are you sure you want to update this record?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, update it!',
      cancelButtonText: 'Cancel'
    }).then(result => {
      if (!result.isConfirmed) return;

      const id = document.getElementById('recordId').value;
      const type = document.getElementById('recordType').value;

      let payload = { id, type };
      switch (type) {
        case 'immediate_material':
          payload.project_name = document.getElementById('im-project_name').value;
          payload.particulars = document.getElementById('im-particulars').value;
          payload.category = document.getElementById('im-category').value;
          payload.amount = document.getElementById('im-amount').value;
          break;
        case 'payroll':
          payload.project_name = document.getElementById('payroll-project_name').value;
          payload.particulars = document.getElementById('payroll-particulars').value;
          payload.category = document.getElementById('payroll-category').value;
          payload.amount = document.getElementById('payroll-amount').value;
          break;
        case 'reimbursements':
          payload.project_name = document.getElementById('reim-project_name').value;
          payload.particulars = document.getElementById('reim-particulars').value;
          payload.employee_name = document.getElementById('reim-employee_name').value;
          payload.amount = document.getElementById('reim-amount').value;
          break;
        case 'misc_expenses':
          payload.project_name = document.getElementById('misc-project_name').value;
          payload.particulars = document.getElementById('misc-particulars').value;
          payload.supplier_name = document.getElementById('misc-supplier_name').value;
          payload.amount = document.getElementById('misc-amount').value;
          break;
        case 'office_expenses':
          payload.particulars = document.getElementById('oe-particulars').value;
          payload.supplier_name = document.getElementById('oe-supplier_name').value;
          payload.amount = document.getElementById('oe-amount').value;
          break;
        case 'utilities_expenses':
          payload.project_name = document.getElementById('ue-project_name').value;
          payload.utility_type = document.getElementById('ue-utility_type').value;
          payload.billing_period = document.getElementById('ue-billing_period').value;
          payload.account_number = document.getElementById('ue-account_number').value;
          payload.amount = document.getElementById('ue-amount').value;
          break;
        case 'sub_contracts':
          payload.project_name = document.getElementById('sub-project_name').value;
          payload.supplier_name = document.getElementById('sub-supplier_name').value;
          payload.particular = document.getElementById('sub-particular').value;
          payload.category = document.getElementById('sub-category').value;
          payload.tcp = document.getElementById('sub-tcp').value;
          break;
      }

      // ✅ Save scroll and tab before reload
      const scrollY = window.scrollY;
      localStorage.setItem('scrollPos', scrollY);
      const activeTab = document.querySelector('.nav-tabs .nav-link.active');
      if (activeTab) {
        localStorage.setItem('activeTab', activeTab.getAttribute('href'));
      }

      // Send to PHP
      fetch('update_record.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
      })
        .then(res => res.json())
        .then(result => {
          if (result.success) {
            Swal.fire({
              title: 'Updated!',
              text: 'Record updated successfully.',
              icon: 'success'
            }).then(() => location.reload());
          } else {
            Swal.fire('Error', result.message || 'Update failed.', 'error');
          }
        })
        .catch(err => {
          console.error('Save error:', err);
          Swal.fire('Error', 'Something went wrong with the update.', 'error');
        });
    });
  });
});

// ✅ Restore scroll position and active tab
window.addEventListener("load", () => {
  const activeTab = localStorage.getItem("activeTab");
  if (activeTab) {
    const tabEl = document.querySelector(`a[href="${activeTab}"]`);
    if (tabEl) {
      if (typeof bootstrap !== "undefined" && bootstrap.Tab) {
        new bootstrap.Tab(tabEl).show();
      } else if (window.jQuery) {
        $(tabEl).tab('show');
      }
    }
    localStorage.removeItem("activeTab");
  }

  const scrollPos = localStorage.getItem("scrollPos");
  if (scrollPos) {
    window.scrollTo(0, scrollPos);
    localStorage.removeItem("scrollPos");
  }
});

</script>


</body>
</html>

<?php
// project_expenses.php
session_start();
require_once "db_connect.php";

$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    echo "<script>alert('No project ID provided.'); window.location.href='projects.php';</script>";
    exit();
}

// Notification count (always available)
$notifCount = 0;
$notifSql = "SELECT COUNT(DISTINCT po_number) AS count FROM purchase_orders WHERE status = 'pending'";
$notifResult = $conn->query($notifSql);
if ($notifResult && $notifResult->num_rows > 0) {
    $notifCount = $notifResult->fetch_assoc()['count'];
}

// 🔹 Current month filter
$currentMonth = date('Y-m'); // e.g. "2025-08"

// 🔹 Special case for OE
if ($project_id === 'OE') {
    $project_name = "Office Expenses";
    $target_budget = 0;
    $address = "-";
    $lot_area = 0;
    $developed_area = 0;
    $start_date = date('Y-m-d');
    $target_completion_date = date('Y-m-d');

    // ✅ Running cost only from office_expenses (released, whole time)
    $sql = "SELECT COALESCE(SUM(amount), 0) 
            FROM office_expenses 
            WHERE status = 'released'";
    $result = $conn->query($sql);
    $runningCost = ($result && $row = $result->fetch_row()) ? $row[0] : 0;

    // ✅ Chart data (released only, current month)
    $sql = "SELECT DATE(created_at) as expense_date, 
                   SUM(amount) as total_expense 
            FROM office_expenses 
            WHERE status = 'released'
              AND DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at)";
    $result = $conn->query($sql);
    $chartData = [];
    while ($row = $result->fetch_assoc()) {
        $chartData[] = $row;
    }

} else {
    // 🔹 Normal project handling
    // Fetch project details
    $sql = "SELECT project_name, target_budget, address, lot_area, developed_area, start_date, target_completion_date 
            FROM projects WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("SQL error (project details): " . $conn->error);
    }
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $stmt->bind_result($project_name, $target_budget, $address, $lot_area, $developed_area, $start_date, $target_completion_date);
    $stmt->fetch();
    $stmt->close();

    // ✅ Running cost (keep whole-time totals as you had)
    $sqlTotals = "
        SELECT
            COALESCE((SELECT SUM(amount) FROM office_expenses WHERE project_id = ? AND status = 'released'), 0),
            COALESCE((SELECT SUM(amount) FROM reimbursements WHERE project_id = ? AND status = 'released'), 0),
            COALESCE((SELECT SUM(amount) FROM misc_expenses WHERE project_id = ? AND status = 'released'), 0),
            COALESCE((SELECT SUM(amount) FROM utilities_expenses WHERE project_id = ? AND status = 'released'), 0),
            COALESCE((SELECT SUM(tcp) FROM sub_contracts WHERE project_id = ? AND status = 'released'), 0),
            COALESCE((SELECT SUM(amount) FROM emergency_released WHERE project_id = ? AND status = 'released'), 0),
            COALESCE((SELECT SUM(amount) FROM immediate_material WHERE project_id = ? AND status = 'released'), 0),
            COALESCE((SELECT SUM(amount) FROM payroll WHERE project_id = ? AND status = 'released'), 0)
    ";
    $stmt = $conn->prepare($sqlTotals);
    $stmt->bind_param("iiiiiiii", $project_id, $project_id, $project_id, $project_id, $project_id, $project_id, $project_id, $project_id);
    $stmt->execute();
    $stmt->bind_result($officeTotal, $reimbursementTotal, $miscTotal, $utilitiesTotal, $subcontractTotal, $emergencyTotal, $immediateTotal, $payrollTotal);
    $stmt->fetch();
    $stmt->close();

    $sqlSummary = "
        SELECT COALESCE(SUM(sa.total_price), 0)
        FROM released_summary sa
        JOIN projects p ON sa.ship_project_name = p.project_name
        WHERE p.id = ?
    ";
    $stmt = $conn->prepare($sqlSummary);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $stmt->bind_result($summaryTotal);
    $stmt->fetch();
    $stmt->close();

    $runningCost = $officeTotal + $reimbursementTotal + $miscTotal + $utilitiesTotal +
                $subcontractTotal + $emergencyTotal + $immediateTotal + $payrollTotal + $summaryTotal;

    // ✅ Chart data (only current month)
    $sql = "
        SELECT expense_date, SUM(amount) as total_expense 
        FROM (
            SELECT DATE(sa.created_at) AS expense_date, sa.total_price AS amount
            FROM released_summary sa
            JOIN projects p ON sa.ship_project_name = p.project_name
            WHERE p.id = ?
              AND DATE_FORMAT(sa.created_at, '%Y-%m') = '$currentMonth'

            UNION ALL

            SELECT DATE(created_at), amount FROM office_expenses WHERE project_id = ? AND status = 'released' AND DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
            UNION ALL
            SELECT DATE(created_at), amount FROM reimbursements WHERE project_id = ? AND status = 'released' AND DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
            UNION ALL
            SELECT DATE(created_at), amount FROM misc_expenses WHERE project_id = ? AND status = 'released' AND DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
            UNION ALL
            SELECT DATE(created_at), amount FROM utilities_expenses WHERE project_id = ? AND status = 'released' AND DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
            UNION ALL
            SELECT DATE(created_at), tcp FROM sub_contracts WHERE project_id = ? AND status = 'released' AND DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
            UNION ALL
            SELECT DATE(released_date), amount FROM emergency_released WHERE project_id = ? AND status = 'released' AND DATE_FORMAT(released_date, '%Y-%m') = '$currentMonth'
            UNION ALL
            SELECT DATE(created_at), amount FROM immediate_material WHERE project_id = ? AND status = 'released' AND DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
            UNION ALL
            SELECT DATE(created_at), amount FROM payroll WHERE project_id = ? AND status = 'released' AND DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
        ) AS combined
        GROUP BY expense_date
        ORDER BY expense_date
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiiiiii", $project_id, $project_id, $project_id, $project_id, $project_id, $project_id, $project_id, $project_id, $project_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $chartData = [];
    while ($row = $result->fetch_assoc()) {
        $chartData[] = $row;
    }
    $stmt->close();
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Project Expenses | <?= htmlspecialchars($project_name) ?></title>
  <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <!-- Bootstrap JS Bundle (with Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
       
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f7f9fc;
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
      background-color: #dc3545; /* Red background */
      color: #fff;
      border: none;
      padding: 5px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 16px;
      width: 100%; /* Full width */
      font-weight: bold;
      text-transform: uppercase;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      transition: background-color 0.3s, transform 0.2s ease-in-out;
    }

    .btn-logout:hover {
      background-color: #c82333; /* Darker red on hover */
      transform: translateY(-2px);
    }

    .btn-logout:active {
      transform: translateY(0);
    }

   .category-container {
  max-width: 960px;
  margin: 60px auto;
  background-color: #ffffff;
  padding: 40px 30px;
  border-radius: 16px;
  border: 1px solid #e1e4e8;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
  text-align: center;
}

.category-btn {
  display: inline-block;
  padding: 14px 26px;
  margin: 12px;
  background-color: #d4af37;
  color: #2f2f2f;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  font-size: 15px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  position: relative;
  overflow: hidden;
  transition: background-color 0.3s ease, transform 0.2s ease;
  box-shadow: 0 4px 10px rgba(17, 103, 177, 0.2);
}

.category-btn::after {
  content: '';
  position: absolute;
  width: 100%;
  height: 3px;
  background-color: #1e1e1e;
  bottom: 0;
  left: 0;
  transform: scaleX(0);
  transform-origin: bottom right;
  transition: transform 0.3s ease;
}

.category-btn:hover {
  background-color: #1e1e1e;
  transform: translateY(-3px);
  color: #fff;
}

.category-btn:hover::after {
  transform: scaleX(1);
  transform-origin: bottom left;
}

.container.content {
  margin-top: 100px;
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
.chart-container {
    width: 90%;
    max-width: 1000px;
    margin: 40px auto;
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    padding: 30px;
}

.top-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 30px;
    position: relative;
}

.budget-info {
    flex: 1;
    min-width: 200px;
    text-align: left;
}

.budget-info p {
    margin: 0;
    font-size: 18px;
    color: #444;
    margin-bottom: 10px;
    margin-top: 25px;
}

.project-title {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    top: -20px;
    width: auto;
    flex: none;
}

.project-title h4 {
    font-size: 24px;
    margin: 0;
    color: #333;
}

canvas {
    width: 100% !important;
    height: auto !important;
}
.main-header {
  font-size: 2.8rem;
  font-weight: 700;
  color: #2f2f2f; /* a strong blue, can adjust */
  text-align: center;
  margin-bottom: 40px;
  position: relative;
  padding-bottom: 12px;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  /* subtle shadow for depth */
  text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
}

.main-header::after {
  content: '';
  display: block;
  width: 80px;
  height: 4px;
  background-color: #d4af37 ;
  margin: 0px auto 0;
  margin-bottom: -40px;
  border-radius: 2px;
  box-shadow: 0 2px 6px rgba(17, 103, 177, 0.4);
}

/* Responsive tweaks for phone screens */
@media (max-width: 768px) {
  /* Sidebar: hide by default on phones */
  .sidebar {
    position: fixed;
    top: 0;
    left: -220px; /* hide offscreen */
    height: 100vh;
    width: 220px;
    padding-top: 60px; /* space for navbar if needed */
    transition: left 0.3s ease;
    z-index: 1000;
  }
  /* Show sidebar when toggled */
  .sidebar.active {
    left: 0;
  }

  /* Adjust sidebar width if collapsed */
  .sidebar.collapsed {
    width: 220px; /* keep full width on phone */
  }

  /* Navbar padding to avoid overlap */
  .navbar {
    padding-left: 15px;
    padding-right: 15px;
  }

  /* Category container width */
  .category-container {
    margin: 20px 10px;
    padding: 20px 15px;
    max-width: 100%;
  }

  /* Category buttons: full width and block for easier tapping */
  .category-btn {
    display: block;
    width: 100%;
    margin: 10px 0;
    font-size: 16px;
    padding: 12px 0;
  }

  /* Badge repositioning */
  .badge {
    top: -18px;
    font-size: 13px;
    padding: 3px 7px;
  }

  /* Chart container: reduce padding */
  .chart-container {
    padding: 20px 15px;
    width: 95%;
    margin: 20px auto;
  }

  /* Top section flex wrap */
  .top-section {
    flex-direction: column;
    align-items: flex-start;
  }

  .budget-info {
    margin-bottom: 15px;
    text-align: left;
    min-width: auto;
  }

  .project-title {
    position: relative;
    left: auto;
    transform: none;
    top: 0;
    margin-bottom: 20px;
    text-align: center;
    width: 100%;
  }

  /* Header font size smaller */
  .main-header {
    font-size: 2rem;
    margin-bottom: 25px;
  }
  .main-header::after {
    width: 60px;
    height: 3px;
    margin-bottom: -25px;
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
    margin-top: 0;
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
}
@media (max-width: 768px) {


.btn-logout {
  margin-top: 10px;
  width: 100%;
  font-size: 14px;
}

.container.content {
  margin-left: 0;
  padding: 10px;
  margin-top: 150px;

}

.project-container {
  padding: 20px 15px;
  margin-top: 100px;
}

.project-container h3 {
  font-size: 24px;
  letter-spacing: 1px;
}

.project-btn {
  padding: 10px 20px;
  font-size: 13px;
  min-width: 150px;
}

.project-card {
  width: 100%;
  max-width: 300px;
  margin: 10px auto;
}

.project-img,
.project-image {
  height: 160px;
}

.badge {
  font-size: 12px;
  padding: 2px 6px;
  top: -20px;
}
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
/* Optional tweak for tighter alignment */
@media (min-width: 768px) {
  .category-container {
    flex-grow: 1;
  }
}

/* Modal Header */
#projectDetailsModal .modal-header {
    background-color: #004080; /* deep blue */
    color: #ffffff;
    border-bottom: 2px solid #003366;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

/* Modal Title */
#projectDetailsModal .modal-title {
    font-weight: 600;
    font-size: 1.25rem;
}

/* Modal Body */
#projectDetailsModal .modal-body {
    background-color: #f9f9f9;
    padding: 25px 30px;
    font-size: 1rem;
    line-height: 1.6;
}

/* Each field block */
#projectDetailsModal .modal-body p {
    margin-bottom: 18px;
    padding: 12px 15px;
    border-left: 4px solid #004080;
    background-color: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    border-radius: 6px;
}

/* Label text */
#projectDetailsModal .modal-body p strong {
    display: inline-block;
    width: 160px;
    color: #2c3e50;
    font-weight: 600;
}


/* Close button override (optional, for better visibility on dark header) */
#projectDetailsModal .btn-close {
    filter: brightness(0) invert(1);
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
<!-- Main Content -->
<div class="container content">
<h3 class="main-header"><?= htmlspecialchars($project_name) ?></h3>


<div class="d-flex flex-wrap align-items-start justify-content-between mb-4">

<!-- Category Container -->
<?php if ($project_id === 'OE'): ?>
  <!-- ✅ Only Office Expenses button when id=OE -->
  <div class="category-container p-4 rounded shadow bg-white">
    <div class="d-flex flex-wrap justify-content-center">
      <a href="office_expenses.php?project_id=<?= $project_id ?>" class="category-btn m-2">Office Expenses</a>
    </div>
  </div>
<?php else: ?>
  <!-- ✅ Normal categories for other projects -->
  <div class="category-container p-4 rounded shadow bg-white">
    <div class="d-flex flex-wrap justify-content-center">
      <a href="office_expenses.php?project_id=<?= $project_id ?>" class="category-btn m-2">Office Expenses</a>
      <a href="misc_expenses.php?project_id=<?= $project_id ?>" class="category-btn m-2">Miscellaneous</a>
      <a href="reimbursement_expenses.php?project_id=<?= $project_id ?>" class="category-btn m-2">Reimbursement</a>
      <a href="Utilities_expenses.php?project_id=<?= $project_id ?>" class="category-btn m-2">Utilities</a>
      <a href="view_subcontract.php?project_id=<?= $project_id ?>" class="category-btn m-2">SubContract</a>
      <a href="payroll_expenses.php?project_id=<?= $project_id ?>" class="category-btn m-2">Payroll</a>
    </div>
  </div>
<?php endif; ?>


<!-- Project Details Button -->
<div class="ms-3 mt-2">
  <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#projectDetailsModal">
    Project Details
  </button>
</div>

</div>

  <!-- YOUR EXPENSES TABLE GOES HERE -->

  <div class="chart-container">
  <div class="top-section">
    <div class="budget-info">
      <p><strong>Target Budget:</strong> ₱<?= number_format($target_budget, 2) ?></p>
      <p><strong>Running Cost:</strong> ₱<?= number_format($runningCost, 2) ?></p>
    </div>

    <div class="project-title">
      <h4>Expenses for Project: <?= htmlspecialchars($project_name) ?></h4>
    </div>
  </div>

  <div style="width: 100%;">
    <canvas id="expensesChart" height="300"></canvas>
  </div>
</div>



<!-- Project Details Modal -->
<div class="modal fade" id="projectDetailsModal" tabindex="-1" aria-labelledby="projectDetailsModalLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="projectDetailsModalLabel">Project Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><strong>Project Name:</strong> <?= htmlspecialchars($project_name) ?></p>
        <p><strong>Address:</strong> <?= htmlspecialchars($address) ?></p>
        <p><strong>Lot Area:</strong> <?= htmlspecialchars($lot_area) ?> m²</p>
        <p><strong>Developed Area:</strong> <?= htmlspecialchars($developed_area) ?></p>
        <p><strong>Target Budget:</strong> ₱<?= number_format($target_budget, 2) ?></p>
        <p><strong>Start Date:</strong> <?= date('F d, Y', strtotime($start_date)) ?></p>
        <p><strong>Target Completion:</strong> <?= date('F d, Y', strtotime($target_completion_date)) ?></p>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
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
  

  const ctx = document.getElementById('expensesChart').getContext('2d');

const chartData = {
  labels: <?= json_encode(array_column($chartData, 'expense_date')) ?>,
  datasets: [{
    label: 'Daily Expenses (₱)',
    data: <?= json_encode(array_map('floatval', array_column($chartData, 'total_expense'))) ?>,
    borderColor: '#1167b1',
    backgroundColor: 'rgba(17, 103, 177, 0.1)',
    fill: true,
    tension: 0.3,
    pointRadius: 5,
    pointHoverRadius: 7
  }]
};

const chartOptions = {
  responsive: true,
  scales: {
    y: {
      beginAtZero: true,
      title: {
        display: true,
        text: 'Amount (₱)'
      }
    },
    x: {
      title: {
        display: true,
        text: 'Date'
      }
    }
  },
  plugins: {
    legend: {
      display: true
    },
    tooltip: {
      callbacks: {
        label: function(context) {
          return '₱' + context.formattedValue;
        }
      }
    }
  }
};

const expensesChart = new Chart(ctx, {
  type: 'line',
  data: chartData,
  options: chartOptions
});

// Calculate Running Cost and insert into DOM
const runningCost = chartData.datasets[0].data.reduce((sum, val) => sum + val, 0);
document.getElementById('running-cost').textContent = runningCost.toLocaleString(undefined, { minimumFractionDigits: 2 });


function toggleSummaryDropdown() {
    var dropdown = document.getElementById("summaryDropdown");
    dropdown.style.display = (dropdown.style.display === "flex") ? "none" : "flex";
  }
</script>
</body>
</html>

<?php $conn->close(); ?>
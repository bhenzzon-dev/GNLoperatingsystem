<?php
session_start();
require_once "db_connect.php";

$project_id = $_GET['project_id'] ?? null;

if (!$project_id) {
    echo "<script>alert('No project ID provided.'); window.location.href='projects.php';</script>";
    exit();
}

// Fetch project name
$stmt = $conn->prepare("SELECT project_name FROM projects WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$stmt->bind_result($project_name);
$stmt->fetch();
$stmt->close();

// Fetch payroll records
$query = "SELECT id, category, particulars, amount, created_at 
          FROM payroll 
          WHERE project_id = ? AND status = 'released' 
          ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

// Notification count (reuse from your existing logic)
$notifCount = 0;
$notifSql = "SELECT COUNT(DISTINCT po_number) AS count FROM purchase_orders WHERE status = 'pending'";
$notifResult = $conn->query($notifSql);
if ($notifResult && $notifResult->num_rows > 0) {
    $notifCount = $notifResult->fetch_assoc()['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reimbursement Expenses | <?= htmlspecialchars($project_name) ?></title>
  <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Poppins', sans-serif; background-color: #f7f9fc; }
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
    .container.content { margin-top: 100px; }
    .table-container {
      background-color: #fff; border-radius: 10px; padding: 30px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); margin-top: -20px; margin-left: 10%;
    }
    table.table { width: 100%; border-collapse: collapse; font-size: 15px; }
    table.table thead th {
      background-color: #1167b1; color: #fff; font-weight: 600;
      padding: 14px 18px; text-align: left;
    }
    table.table tbody td {
      padding: 12px 18px; border-bottom: 1px solid #e0e0e0; color: #333;
    }
    table.table tbody tr:hover { background-color: #f4f8fb; }
    .btn-info {
      background-color: #17a2b8; border: none; font-weight: 500;
      padding: 6px 12px; border-radius: 6px;
    }
    .btn-info:hover { background-color: #138496; color: #fff; }
    h3.text-center {
      font-weight: 500; font-size: 26px; color: #333; margin-top: 20px;
      text-transform: uppercase; letter-spacing: 0.5px; margin-left: 10%;
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
@media (max-width: 768px) {
  .sidebar {
    position: relative;
    width: 100%;
    height: auto;
    flex-direction: row;
    flex-wrap: wrap;
    padding: 10px;
    justify-content: space-around;
    border-right: none;
    box-shadow: none;
    top: 0;
  }

  .sidebar a {
    padding: 8px 10px;
    font-size: 12px;
    margin: 5px;
  }

  .btn-logout {
    font-size: 14px;
    padding: 5px 10px;
    width: auto;
  }

  .table-container {
    margin-left: 0;
    padding: 15px;
  }

  h3.text-center {
    margin-left: 0;
    font-size: 20px;
    text-align: center;
  }

  table.table {
    font-size: 13px;
  }

  table.table thead th,
  table.table tbody td {
    padding: 10px;
  }

  .navbar-brand img {
    height: 24px;
  }

  .container.content {
    margin-top: 80px;
    padding: 0 10px;
  }

  .badge {
    top: -10px;
    font-size: 12px;
  }
}


  </style>
</head>
<body>
<div class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('active')">
  <span></span>
  <span></span>
  <span></span>
</div>

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
          <a class="nav-link text-light" href="admin_summary_declined.php">Declined Request</a>
        </li>
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

  <a href="admin_summary_request.php">
    Summary Requests
    <?php if ($notifCount > 0): ?>
      <span class="badge badge-warning ml-2"><?= $notifCount ?></span>
    <?php endif; ?>
  </a>

  <a href="admin_summary_approved.php">Summary Approved</a>
  <a href="admin_sub_contract.php">Add Sub Contracts</a>
  <button onclick="window.location.href='admin_logout.php'" class="btn btn-dark mt-3">Logout</button>
</div>

<div class="container content">
  <h3 class="text-center mb-4">Payroll Expenses for Project: <?= htmlspecialchars($project_name) ?></h3>

  <div class="table-container">
    <table class="table table-striped">
    <table class="table table-striped">
  <thead>
    <tr>
      <th>Category</th>
      <th>Particulars</th>
      <th>Amount</th>
      <th>Date Added</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['category']) ?></td>
          <td><?= htmlspecialchars($row['particulars']) ?></td>
          <td><?= number_format($row['amount'], 2) ?></td>
          <td><?= date('F d, Y h:i A', strtotime($row['created_at'])) ?></td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="4" class="text-center">No payroll records found.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
  </div>

  <div class="text-center mb-4">
    <?php if (isset($_SERVER['HTTP_REFERER'])): ?>
      <div class="mt-3 text-left">
        <a href="<?= htmlspecialchars($_SERVER['HTTP_REFERER']) ?>" class="btn btn-secondary">← Back</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>

<script>
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  const content = document.querySelector('.container.project-container');

  sidebarToggle.addEventListener('click', function () {
    sidebar.classList.toggle('collapsed');

    if (sidebar.classList.contains('collapsed')) {
      content.style.marginLeft = '0';
    } else {
      content.style.marginLeft = '240px';
    }
  });
</script>
</body>
</html>

<?php $conn->close(); ?>

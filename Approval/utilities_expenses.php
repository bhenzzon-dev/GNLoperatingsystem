<?php
session_start();
require_once 'db_connect.php';

// Fetch utilities expenses and join with the projects table to get project name
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id > 0) {
    $sql = "SELECT ue.id, p.project_name, ue.utility_type, ue.billing_period, ue.account_number, ue.amount, ue.created_at
            FROM utilities_expenses ue
            JOIN projects p ON ue.project_id = p.id
            WHERE ue.project_id = $project_id
            ORDER BY ue.created_at DESC";

    // Optionally fetch the project name for the page header
    $projectNameResult = $conn->query("SELECT project_name FROM projects WHERE id = $project_id");
    $projectNameRow = $projectNameResult->fetch_assoc();
    $project_name = $projectNameRow['project_name'] ?? 'Unknown Project';
} else {
    $sql = "SELECT ue.id, p.project_name, ue.utility_type, ue.billing_period, ue.account_number, ue.amount, ue.created_at
            FROM utilities_expenses ue
            JOIN projects p ON ue.project_id = p.id
            ORDER BY ue.created_at DESC";
    $project_name = "All Projects";
}

$result = $conn->query($sql);

// Check for success message
$successMessage = isset($_GET['success']) ? true : false;

$notifCount = 0;
$notifSql = "SELECT COUNT(DISTINCT po_number) AS count FROM purchase_orders WHERE status = 'pending'"; // Change table/column if needed
$notifResult = $conn->query($notifSql);
if ($notifResult && $notifResult->num_rows > 0) {
    $notifCount = $notifResult->fetch_assoc()['count'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Utilities Expenses | Admin Panel</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- SweetAlert CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Add your CSS styling here */
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
            background-color: #dc3545;
            color: #fff;
            border: none;
            padding: 5px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
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
            margin-left: 240px;
            padding: 20px;
        }

        /* Table Styles */
        .table-container {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            margin-top: 10%;
            width: 120%;
            margin-left: 10%;
        }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 15px;
            color: #333;
        }

        /* Table Header */
        th {
            background-color: #1167b1;
            color: #fff;
            text-align: left;
            font-weight: 600;
            padding: 16px 24px;
            border-bottom: 2px solid #0d4f8b;
        }

        /* Table Body */
        td {
            background-color: #f9f9f9;
            padding: 14px 24px;
            border-bottom: 1px solid #e1e4e8;
        }

        /* Row Hover Effect */
        tr:hover td {
            background-color: #eef4fb;
        }

        /* Optional: Add rounded corners to first & last cell */
        table tr:first-child th:first-child {
            border-top-left-radius: 8px;
        }

        table tr:first-child th:last-child {
            border-top-right-radius: 8px;
        }

        .text-center {
            margin-bottom: 30px;
            font-weight: 400;
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
        position: fixed;
        top: 56px; /* Adjust if navbar height changes */
        left: 0;
        width: 100%;
        height: auto;
        flex-direction: row;
        overflow-x: auto;
        padding: 10px;
        border-right: none;
        border-bottom: 3px solid #444;
        z-index: 999;
    }

    .sidebar a {
        flex: 1;
        padding: 10px 12px;
        margin: 0 4px;
        font-size: 12px;
        white-space: nowrap;
        text-align: center;
    }

    .btn-logout {
        font-size: 14px;
        padding: 5px;
    }

    .container.content {
        margin: 0;
        padding: 15px;
    }

    .table-container {
        width: 100%;
        margin: 100px 0 0 0;
        overflow-x: auto;
        padding: 15px;
    }

    table {
        width: 700px; /* allow horizontal scroll on small screens */
    }

    .badge {
        top: -15px;
        font-size: 12px;
        padding: 3px 6px;
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

<!-- Main Content -->
<div class="container content">

    <!-- Success Message -->
    <?php if ($successMessage): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'The utilities expense has been successfully added.',
                confirmButtonText: 'OK'
            });
        </script>
    <?php endif; ?>

    <div class="table-container">
        <h3 class="text-center">Utilities Expenses for <?= htmlspecialchars($project_name) ?></h3>
        <table>
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Utility Type</th>
                    <th>Billing Period</th>
                    <th>Account Number</th>
                    <th>Amount</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['project_name']) ?></td>
                            <td><?= htmlspecialchars($row['utility_type']) ?></td>
                            <td><?= htmlspecialchars($row['billing_period']) ?></td>
                            <td><?= htmlspecialchars($row['account_number']) ?></td>
                            <td><?= number_format($row['amount'], 2) ?></td>
                            <td><?= date('F d, Y h:i A', strtotime($row['created_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No utilities expenses found.</td>
                    </tr>
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

<!-- Bootstrap & dependencies -->
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>

<!-- Sidebar Toggle Script -->
<script>
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.sidebar');

  sidebarToggle.addEventListener('click', function() {
    sidebar.classList.toggle('collapsed');
  });
</script>

</body>
</html>

<?php
$conn->close();
?>

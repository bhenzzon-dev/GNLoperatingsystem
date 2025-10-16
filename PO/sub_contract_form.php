<?php
session_start();
require_once 'db_connect.php';

// Fetch projects to populate the dropdown for project_id
$projectsQuery = "SELECT id, project_name FROM projects ORDER BY project_name ASC";
$projectsResult = $conn->query($projectsQuery);

// Check for success message from redirect
$successMessage = isset($_GET['success']) ? true : false;

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
    <title>Subcontract Form | Admin Panel</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
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

        /* Sidebar Styles */
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
            margin-left: 240px;
            padding: 20px;
        }

        /* Form Section */
        .container.form-container {
            background-color: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 80px;
        }

        .form-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .form-group {
            width: 48%; /* Adjust width to ensure both columns fit side-by-side */
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .form-group input[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .form-group input[type="submit"]:hover {
            background-color: #45a049;
        }

        label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
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

<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <a class="navbar-brand" href="finance_index.php">
        <img src="/gnlproject/img/logo.png" alt="Logo">
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

<!-- Main Content -->
<div class="container form-container">
    <h3>Subcontract Form</h3>

    <form action="submit_subcontract.php" method="POST">
        <div class="form-row">
            <div class="form-group col">
                <label for="project_id">Project</label>
                <select id="project_id" name="project_id" required>
                    <option value="">Select Project</option>
                    <?php while ($row = $projectsResult->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['project_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group col">
                <label for="category">Category</label>
                <select name="category" id="category" class="form-control" required>
                    <option value="Structural">Structural</option>
                    <option value="Plumbing">Plumbing</option>
                    <option value="Electrical">Electrical</option>
                    <option value="Finishing">Finishing</option>
                    <option value="Carpentry">Carpentry</option>
                    <option value="Flooring">Flooring</option>
                    <option value="HVAC">HVAC</option>
                    <option value="Masonry">Masonry</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col">
                <label for="supplier_name">Supplier</label>
                <input type="text" id="supplier_name" name="supplier_name" required placeholder="Enter supplier name">
            </div>

            <div class="form-group col">
                <label for="contact_number">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" required placeholder="Enter contact number">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col">
                <label for="tcp">Total Cost Project (TCP)</label>
                <input type="number" id="tcp" name="tcp" required placeholder="Enter total cost project">
            </div>

            <div class="form-group col">
                <label for="particular">Particular</label>
                <textarea id="particular" name="particular" rows="4" placeholder="Enter particular details"></textarea>
            </div>
        </div>

        <div class="form-group">
            <input type="submit" value="Submit Subcontract">
        </div>
    </form>
</div>


<!-- SweetAlert Notification Script -->
<script>
    <?php if ($successMessage): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: 'The subcontract has been successfully added.',
            confirmButtonText: 'OK'
        });
    <?php endif; ?>
</script>

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

<?php
$conn->close();
?>

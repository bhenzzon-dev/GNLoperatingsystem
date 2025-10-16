<?php
session_start();
require_once 'db_connect.php';

// Fetch projects to populate the dropdown
$projectsQuery = "SELECT id, project_name FROM projects ORDER BY project_name ASC";
$projectsResult = $conn->query($projectsQuery);

// Count pending MRFs
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
    <title>Immediate Material Form | Admin Panel</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert -->
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

        .container.content {
            margin-left: 240px;
            padding: 20px;
        }

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
            width: 48%;
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
    <a href="finance_index.php" style="position: relative;">
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
        <li class="nav-item"><a class="nav-link" href="released_pdf.php">Released Summary</a></li>
        <li class="nav-item"><a class="nav-link" href="summary_approved.php">Approved Request</a></li>
            <li class="nav-item"><a class="nav-link" href="summary_declined.php">Declined Request</a></li>
            <li class="nav-item"><a class="nav-link" href="summary_request.php">Requested PO</a></li>
        </ul>
    </div>
</nav>

<!-- Main Content -->
<div class="container form-container">
    <h3>Immediate Material Form</h3>
    <form action="submit_immediate_material.php" method="POST">
        <div class="form-row">
            <div class="form-group col">
                <label for="project_id">Project Name</label>
                <select id="project_id" name="project_id" required>
                    <option value="">Select Project</option>
                    <?php while ($row = $projectsResult->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['project_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group col">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="Structural">Structural</option>
                        <option value="Plumbing">Plumbing</option>
                        <option value="Electrical">Electrical</option>
                        <option value="Finishing">Finishing</option>
                        <option value="Hardware">Hardware</option>
                        <option value="Raw_Material">Raw Material</option>
                        <option value="Welding">Welding</option>
                        <option value="Construction">Construction</option>
                        <option value="Funding">Funding</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col">
                <label for="particulars">Particulars</label>
                <textarea id="particulars" name="particulars" rows="3" required placeholder="Enter details"></textarea>
            </div>
            <div class="form-group col">
                <label for="amount">Amount</label>
                <input type="number" step="0.01" min="0" id="amount" name="amount" required placeholder="Enter amount">
            </div>
        </div>
        <div class="form-group">
            <input type="submit" value="Submit Material Request">
        </div>
    </form>
</div>

<script>
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    sidebarToggle.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
    });

    function updateMRFCount() {
        fetch('get_pending_count.php')
            .then(response => response.text())
            .then(count => {
                const badge = document.getElementById('mrf-badge');
                if (parseInt(count) > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(err => console.error('Error fetching MRF count:', err));
    }

    setInterval(updateMRFCount, 5000);

    //swallfire

    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === '1') {
            Swal.fire({
                icon: 'success',
                title: 'Request Submitted',
                text: 'Your request has been successfully submitted!',
                confirmButtonColor: '#28a745'
            });
            // Remove the query param without reloading
            window.history.replaceState(null, null, window.location.pathname);
        }
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

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>

</body>
</html>

<?php $conn->close(); ?>

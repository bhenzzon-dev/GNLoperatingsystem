<?php
session_start();
require_once 'db_connect.php';

// Fetch projects to populate the dropdown for project_id
$projectsQuery = "SELECT id, project_name FROM projects ORDER BY project_name ASC";
$projectsResult = $conn->query($projectsQuery);

// Check for success message from redirect
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
    <title>Subcontract Form | Admin Panel</title>
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
        .form-header {
  display: flex;
  align-items: center;
  border-bottom: 2px solid #d4af37;
  padding-bottom: 10px;
}

.header-logo {
  height: 30px;
  margin-right: 10px;
}

.form-title {
  color: #d4af37;
  font-weight: bold;
  margin: 0;
}

.form-group label {
  color: #2a2d34; /* gunmetal gray */
  font-weight: 600;
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
        .btn-submit {
  background-color: #d4af37 !important;  /* Gold */
  color: #2a2d34 !important;             /* Gunmetal gray */
  font-weight: 600;
  border: none;
  border-radius: 6px;
  padding: 12px 20px;
  width: 100%;
  transition: background-color 0.3s ease, transform 0.2s ease;
}

.btn-submit:hover {
  background-color: #c29e2c !important;  /* Darker gold */
  transform: translateY(-2px);
  cursor: pointer;
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
@media (max-width: 480px) {
    .navbar-brand img {
        height: 24px;
    }

    .nav-link {
        font-size: 14px;
    }

    label {
        font-size: 14px;
    }

    .btn-logout {
        font-size: 14px;
        padding: 8px;
    }

    .badge {
        font-size: 13px;
        top: -20px;
    }
}
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    top: 0;
    left: -220px;
    height: 100%;
    width: 220px;
    padding-top: 60px;
    background-color: #333;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
    transition: left 0.3s ease;
    z-index: 1100;
    overflow-y: auto;
  }

  .sidebar.active {
    left: 0;
  }
  
  .container.content {
    margin-left: 0;
    padding: 15px;
  }
}

 /* Desktop & tablet: two columns */
.form-row {
    display: flex;
    justify-content: space-between;
    gap: 20px; /* space between columns */
    margin-bottom: 15px;
    flex-wrap: nowrap; /* prevent wrapping on desktop */
}

.form-group {
    flex: 1 1 48%; /* grow/shrink, base 48% width */
}

/* Mobile: stack vertically, no compression */
@media (max-width: 768px) {
    .container.content {
        margin-left: 0;
        padding: 15px;
    }

    .container.form-container {
        margin-top: 20px;
        padding: 20px;
    }

    .form-row {
        display: block; /* stack vertically */
        margin-bottom: 20px;
    }

    .form-group {
        width: 100% !important;
        margin-bottom: 20px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 12px; /* more padding for easier tapping */
        font-size: 16px;

    }
    .form-control {
      z-index: 2;
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
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    top: 56px; /* below navbar */
    left: 0;
    width: 100%;
    background-color: var(--gray-light);
    display: flex;
    flex-direction: column;
    max-height: 0; /* collapsed */
    overflow: hidden;
    transition: max-height 0.4s ease;
    z-index: 999;
    border-bottom: 3px solid #444;
    margin-top: 44px;
  }

  .sidebar.open {
    max-height: 500px; /* expanded, adjust height */
  }

  .sidebar a,
  .dropdown-toggle-link {
    padding: 10px 16px;
    font-size: 14px;
    white-space: nowrap;
    text-align: left;
  }

  .dropdown-content {
    display: none;
    padding-left: 20px;
  }

  .dropdown-sidebar.active .dropdown-content {
    display: flex;
    flex-direction: column;
  }

  .container.content {
    margin-left: 0;
    margin-top: 150px; /* adjust based on sidebar height */
  }
  .form-container {
    position: relative !important;  /* remove absolute positioning */
    top: 160px !important;
    left: auto !important;
    transform: none !important;
    margin-top: 150px; /* increase this to move down more */
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
<!-- Main Content -->
<div class="container form-container">
  <div class="form-header d-flex align-items-center mb-4">
    <img src="/gnlproject/img/logo.png" alt="Logo" class="header-logo mr-2">
    <h3 class="form-title">Subcontract Form</h3>
  </div>

    <form action="admin_submit_subcontract.php" method="POST">
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
  <input type="submit" value="Submit Subcontract" class="btn-submit">
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

  function toggleSummaryDropdown() {
    var dropdown = document.getElementById("summaryDropdown");
    dropdown.style.display = (dropdown.style.display === "flex") ? "none" : "flex";
  }
</script>

</body>
</html>

<?php
$conn->close();
?>

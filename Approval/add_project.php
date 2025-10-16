<?php
// add_project.php

session_start();  
require_once "db_connect.php";

// initialize
$project_name = $project_desc = "";
$name_err = $desc_err = "";
$success_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // safely grab POST
    $project_name = trim($_POST['project_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $title_number = trim($_POST['title_number'] ?? '');
    $lot_area = $_POST['lot_area'] ?? 0;
    $developed_area = $_POST['developed_area'] ?? 0;
    $target_budget = $_POST['target_budget'] ?? 0;
    $start_date = $_POST['start_date'] ?? '';
    $target_completion_date = $_POST['target_completion_date'] ?? '';

    // handle image upload
    $image_path = null;
    if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/projects/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_tmp = $_FILES['project_image']['tmp_name'];
        $file_name = basename($_FILES['project_image']['name']);
        $target_file = $upload_dir . uniqid() . '_' . $file_name;

        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($file_tmp, $target_file)) {
                $image_path = $target_file;
            } else {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Image Upload Failed',
                            text: 'Could not upload the image.'
                        });
                    });
                </script>";
            }
        } else {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Invalid Image Format',
                        text: 'Only JPG, JPEG, PNG, and GIF are allowed.'
                    });
                });
            </script>";
        }
    }

    // validate
    if ($project_name === "") {
        $name_err = "Please enter a project name.";
    }

    // insert if valid
    if (empty($name_err) && empty($desc_err)) {
        $sql = "INSERT INTO projects (project_name, address, title_number, lot_area, developed_area, target_budget, start_date, target_completion_date, image_path, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssdsdsss", $project_name, $address, $title_number, $lot_area, $developed_area, $target_budget, $start_date, $target_completion_date, $image_path);

            if ($stmt->execute()) {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Project added successfully!',
                            confirmButtonColor: '#46cb18'
                        }).then(() => {
                            window.location.href = 'add_project.php';
                        });
                    });
                </script>";
                $project_name = $project_desc = "";
            } else {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Something went wrong. Please try again.'
                        });
                    });
                </script>";
            }
            $stmt->close();
        }
    }

}  
$notifCount = 0;
$notifSql = "SELECT COUNT(DISTINCT po_number) AS count FROM purchase_orders WHERE status = 'pending'"; // Change table/column if needed
$notifResult = $conn->query($notifSql);
if ($notifResult && $notifResult->num_rows > 0) {
    $notifCount = $notifResult->fetch_assoc()['count'];
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Project | Admin Panel</title>
  <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap 4 -->
  <link
    rel="stylesheet"
    href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css"
    crossorigin="anonymous"
  >
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
       
<link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;600&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
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

    .container-form {
  position: absolute;
  top: 55%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #ffffff;
  padding: 40px 35px;
  border-radius: 10px;
  box-shadow: 0 10px 35px rgba(0, 0, 0, 0.15);
  max-width: 500px;
  width: 100%;
  color: #333;
  z-index: 10;
  border: 1px solid #e0e0e0;
}

.container-form h2 {
  font-size: 26px;
  text-align: center;
  margin-bottom: 25px;
  font-weight: 600;
  color: #1167b1;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.form-group label {
  font-weight: 500;
  color: #444;
  margin-bottom: 6px;
  display: block;
  font-size: 14px;
}

.form-control {
  border-radius: 8px;
  padding: 12px 16px;
  background: #f9f9f9;
  border: 1px solid #ccc;
  box-shadow: none;
  transition: all 0.2s ease;
  font-size: 15px;
}

.form-control:focus {
  border-color: #1167b1;
  box-shadow: 0 0 0 3px rgba(17, 103, 177, 0.15);
  background-color: #fff;
}

.btn-add {
  background-color: #1167b1;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 12px 18px;
  width: 100%;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: background-color 0.3s ease, transform 0.2s ease;
  font-size: 15px;
  cursor: pointer;
}

.btn-add:hover {
  background-color: #0d4f8b;
  transform: translateY(-2px);
}

.text-error {
  color: #d9534f;
  font-size: 14px;
  margin-top: -15px;
  margin-bottom: 10px;
}

.text-success {
  color: #28a745;
  text-align: center;
  font-size: 15px;
  margin-bottom: 20px;
  font-weight: 500;
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
  .container-form {
    position: relative !important;  /* remove absolute positioning */
    top: 150px !important;
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

<div class="container-form" style="background-color: white; color: #fff; padding: 30px; border-radius: 10px; max-width: 900px; margin: auto; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);">
  <h2 class="d-flex align-items-center mb-4" style="color: #d4af37;">
    <img src="/gnlproject/img/logo.png" alt="Logo" style="height: 30px; margin-right: 10px;">
    Add New Project
  </h2>

  <form action="add_project.php" method="post" enctype="multipart/form-data">
    <div class="form-row">
      <div class="form-group col">
        <label for="project_name" style="color:#2a2d34;">Project Name</label>
        <input
          type="text"
          name="project_name"
          id="project_name"
          class="form-control <?= $name_err ? 'is-invalid' : '' ?>"
          value="<?= htmlspecialchars($project_name) ?>"
          required>
        <?php if ($name_err): ?>
          <div class="text-error" style="color: #f88;"><?= $name_err ?></div>
        <?php endif; ?>
      </div>

      <div class="form-group col">
        <label for="address" style="color: #2a2d34;">Address</label>
        <input type="text" name="address" id="address" class="form-control" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group col">
        <label for="title_number" style="color: #2a2d34;">Title Number</label>
        <input type="text" name="title_number" id="title_number" class="form-control" required>
      </div>

      <div class="form-group col">
        <label for="lot_area" style="color: #2a2d34;">Lot Area (sqm)</label>
        <input type="number" name="lot_area" id="lot_area" class="form-control" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group col">
      <label for="developed_area" style="color: #2a2d34;">Developed Area</label>
        <input type="text" name="developed_area" id="developed_area" class="form-control" required>
        </div>
    </div>

    <div class="form-row">
      <div class="form-group col">
        <label for="target_budget" style="color: #2a2d34;">Target Budget (₱)</label>
        <input type="number" name="target_budget" id="target_budget" class="form-control" required>
      </div>

      <div class="form-group col">
        <label for="start_date" style="color: #2a2d34;">Start Date</label>
        <input type="date" name="start_date" id="start_date" class="form-control" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group col">
        <label for="target_completion_date" style="color: #2a2d34;">Target Completion Date</label>
        <input type="date" name="target_completion_date" id="target_completion_date" class="form-control" required>
      </div>
      <div class="form-group col">
        <label for="project_image" style="color: #2a2d34;">Project Image</label>
        <input type="file" name="project_image" id="project_image" class="form-control" accept="image/*">
      </div>
    </div>

    <button type="submit" class="btn btn-block" style="background-color: #d4af37; color: #2a2d34; font-weight: bold;">Add Project</button>
  </form>
</div>



  <!-- Bootstrap & dependencies -->
  <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>
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

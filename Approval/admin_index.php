<?php
// projects.php
session_start();
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

require_once "db_connect.php";

// Fetch all projects
$sql = "SELECT id, project_name, image_path FROM projects ORDER BY created_at DESC";
$result = $conn->query($sql);

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
  <title>Admin | Approval Panel</title>
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

    .container.content {
      margin-left: 240px; /* Allow space for the sidebar */
      padding: 20px;
    }

    /* Title Style */
    .project-container {
  background: linear-gradient(to bottom right, #ffffff, #ffffff, #ffffff);
  border-radius: 16px;
  padding: 40px 30px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.07);
  margin-top: 80px;
  max-width: 1000px;
  margin-left: auto;
  margin-right: auto;
}

.project-container h3 {
  font-weight: 700;
  font-size: 36px; /* Slightly larger for more impact */
  color: #2c3e50;
  text-transform: uppercase;
  letter-spacing: 2px; /* Increased spacing for a more polished look */
  margin-bottom: 20px; /* Reduced margin for a tighter layout */
  text-align: center;
  position: relative; /* To add decorative underline effect */
  padding-bottom: 10px;
}

.project-container h3::after {
  content: '';
  position: absolute;
  width: 80%;
  height: 3px;
  background-color: #d4af37; /* Highlight color */
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  border-radius: 5px;
}


/* Flex Layout */
.d-flex {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 20px;
}

/* Button Style */
.project-btn {
  background: linear-gradient(145deg, #d4af37, #d4af37);
  color: #ffffff;
  border: none;
  padding: 14px 30px;
  font-size: 15px;
  font-weight: 600;
  text-transform: uppercase;
  border-radius: 12px;
  cursor: pointer;
  min-width: 200px;
  transition: all 0.3s ease;
  box-shadow: 0 6px 12px rgba(0, 123, 255, 0.25);
  position: relative;
  overflow: hidden;
}

.project-btn::after {
  content: '';
  position: absolute;
  width: 100%;
  height: 3px;
  background-color: #fff;
  bottom: 0;
  left: 0;
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.3s ease;
}

.project-btn:hover {
  background: linear-gradient(145deg, #d4af37, #d4af37);
  box-shadow: 0 8px 16px rgba(0, 123, 255, 0.35);
  transform: translateY(-3px);
}

.project-btn:hover::after {
  transform: scaleX(1);
}

.project-btn:active {
  transform: translateY(0);
  box-shadow: 0 4px 8px rgba(0, 123, 255, 0.2);
}

.project-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
  width: 180px;
  text-align: center;
  transition: transform 0.3s ease;
  margin: 10px;
}

.project-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.15);
}

.card-link {
  text-decoration: none;
  color: #2c3e50;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.project-image {
  width: 100%;
  height: 140px;
  object-fit: cover;
  border-radius: 12px;
  margin-bottom: 12px;
}

.project-title {
  font-weight: 600;
  font-size: 16px;
  text-transform: uppercase;
}
.project-img {
  width: 100%;
  height: 200px;
  object-fit: cover;
  border-radius: 12px;
  margin-bottom: 10px;
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


  .btn-logout {
    margin-top: 10px;
    width: 100%;
    font-size: 14px;
  }

  .container.content {
    margin-left: 0;
    padding: 10px;
 
  }

  .project-container {
    padding: 20px 15px;
    margin-top: 210px;
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
    margin-top: 90px;
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
<div class="container project-container">
  <h3 class="mb-4 text-center">GNL PROJECTS</h3>

  <div class="d-flex flex-wrap justify-content-center">
<?php if ($result && $result->num_rows > 0): ?>
  <?php while ($row = $result->fetch_assoc()): ?>
    <div class="project-card">
      <a href="project_expenses.php?id=<?= $row['id'] ?>" class="card-link">
        <img src="<?= htmlspecialchars($row['image_path']) ?>" alt="Project Image" class="project-img">
        <div class="project-title"><?= htmlspecialchars($row['project_name']) ?></div>
      </a>
    </div>
  <?php endwhile; ?>
<?php endif; ?>

    <!-- ✅ Extra static OE card -->
    <div class="project-card">
      <a href="project_expenses.php?id=OE" class="card-link">
        <img src="/gnlproject/img/oe.png" alt="OE Image" class="project-img"> <!-- set your own icon -->
        <div class="project-title">Office Expenses</div>
      </a>
    </div>

  </div>
</div>

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" role="dialog" aria-labelledby="feedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form action="submit_feedback.php" method="POST" enctype="multipart/form-data" class="modal-content" id="feedbackForm">
    <div class="modal-header" style="background-color: #2a2d34; color: #d4af37;">
  <h5 class="modal-title d-flex align-items-center" id="feedbackModalLabel">
    <img src="/gnlproject/img/logo.png" alt="Logo" style="height: 25px; margin-right: 10px;">
    Feedback
  </h5>
  <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>

      <div class="modal-body">
        <div class="form-group">
          <label for="employee_name">Employee Name</label>
          <input type="text" name="employee_name" id="employee_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="project_name">Project Name</label>
          <input type="text" name="project_name" id="project_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="concern">Concern</label>
          <textarea name="concern" id="concern" class="form-control" rows="4" required></textarea>
        </div>
        <div class="form-group">
          <label for="image">Upload Image</label>
          <input type="file" name="image" id="image" class="form-control-file" accept="image/*">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Submit Feedback</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Bootstrap & dependencies -->
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Sidebar Toggle Script -->
<script>
  const toggleSidebarBtn = document.querySelector('#sidebarToggle');
const sidebar = document.querySelector('.sidebar');

toggleSidebarBtn?.addEventListener('click', () => {
  if (window.innerWidth <= 768) {
    // Mobile → slide in/out
    sidebar.classList.toggle('open');
  } else {
    // Desktop → collapse/expand width
    sidebar.classList.toggle('collapsed');
  }
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

<?php
if (isset($result)) { $result->free(); }
$conn->close();
?>

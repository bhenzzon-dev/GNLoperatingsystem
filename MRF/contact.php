<?php
session_start();
if (!isset($_SESSION["architect_loggedin"]) || $_SESSION["architect_loggedin"] !== true) {
    header("location: architect_login.php");
    exit;
}

$successMsg = '';
$errorMsg = '';

include 'db_connect.php';  // your DB connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $employee_name = trim($_POST['employee_name'] ?? '');
    $project_name = trim($_POST['project_name'] ?? '');
    $concern = trim($_POST['concern'] ?? '');

    if (empty($employee_name) || empty($project_name) || empty($concern)) {
        $errorMsg = "Please fill in all required fields.";
    } else {
        // Handle file upload if any
        $uploadOk = true;
        $image_path = null;
        if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] != UPLOAD_ERR_NO_FILE) {
            $targetDir = "uploads/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $fileName = basename($_FILES["image_path"]["name"]);
            $safeFileName = time() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
            $targetFilePath = $targetDir . $safeFileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];

            if (!in_array($fileType, $allowedTypes)) {
                $errorMsg = "Sorry, only JPG, JPEG, PNG, GIF & BMP files are allowed.";
                $uploadOk = false;
            }

            if ($uploadOk) {
                if (move_uploaded_file($_FILES["image_path"]["tmp_name"], $targetFilePath)) {
                    $image_path = $targetFilePath;
                } else {
                    $errorMsg = "There was an error uploading your file.";
                    $uploadOk = false;
                }
            }
        }

        if ($uploadOk) {
            // Prepare and execute insert query using mysqli prepared statement
            $stmt = $conn->prepare("INSERT INTO feedback (employee_name, project_name, concern, image_path) VALUES (?, ?, ?, ?)");
            if ($stmt === false) {
                $errorMsg = "Prepare failed: (" . $conn->errno . ") " . $conn->error;
            } else {
                // bind parameters
                $stmt->bind_param("ssss", $employee_name, $project_name, $concern, $image_path);
                if ($stmt->execute()) {
                    $successMsg = "Feedback submitted successfully!";
                    // Clear form variables to reset the form if you want
                    $employee_name = $project_name = $concern = '';
                } else {
                    $errorMsg = "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

$mrfResult = $conn->query("SELECT COUNT(*) AS total FROM mrf");
$requestedResult = $conn->query("SELECT COUNT(*) AS total FROM requested_mrf");

$mrfCount = $mrfResult->fetch_assoc()['total'];
$requestedCount = $requestedResult->fetch_assoc()['total'];

$totalSubmitted = $mrfCount + $requestedCount;
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Submit Feedback</title>
  <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link
    rel="stylesheet"
    href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css"
  >
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
     body {
  font-family: 'Poppins', sans-serif;
  background-color: #f7f9fc;
  margin: 0;
  padding: 0;
}

.navbar {
  background: #2c3e50;
  backdrop-filter: blur(8px);
}

.navbar-brand img {
  height: 30px;
}

.nav-link {
  font-weight: 500;
  color: #fff !important; /* Make the text color white */
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
  justify-content: flex-start; /* Align items to the top */
  border-right: 3px solid #444;
  box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); /* Adding a subtle shadow */
}

.sidebar a {
  color: #fff;
  text-decoration: none;
  padding: 8px 20px;
  margin: 10px 0; /* Small margin for spacing between links */
  border-radius: 6px;
  transition: background-color 0.3s ease;
  font-weight: 500;
  text-transform: uppercase;
}

.sidebar a:hover {
  background-color: #555;
}

.btn-logout {
  background-color: #dc3545; /* Red background */
  color: #fff;
  border: none;
  padding: 5px 20px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 16px;
  margin-top: 320%; /* Push it to the bottom */
  width: 100%; /* Full width */
  font-weight: bold;
  text-transform: uppercase;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
  transition: background-color 0.3s, transform 0.2s ease-in-out;
}

.btn-logout:hover {
  background-color: #c82333; /* Darker red on hover */
  transform: translateY(-2px); /* Slightly lift the button on hover */
}

.btn-logout:active {
  transform: translateY(0); /* Return to normal when clicked */
}

    .container.content {
      margin-left: 240px;
      padding: 40px 20px;
      margin-top: 5%;
    }

    .contact-form {
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.07);
      max-width: 800px;
      margin: 0 auto;
    }

    .contact-form h3 {
      text-align: center;
      margin-bottom: 25px;
      font-weight: bold;
    }

    .form-control {
      border-radius: 8px;
    }

    .contact-form button {
      background: #1167b1;
      color: #fff;
      border: none;
      padding: 10px;
      width: 100%;
      border-radius: 8px;
      font-weight: bold;
      text-transform: uppercase;
      margin-top: 10px;
    }

    .contact-form button:hover {
      background: #0f5a99;
    }

    .alert {
      max-width: 800px;
      margin: 20px auto;
      border-radius: 8px;
    }

    @media (max-width: 768px) {
  .sidebar {
    position: relative;
    width: 100%;
    height: auto;
    flex-direction: row;
    overflow-x: auto;
    white-space: nowrap;
    padding: 10px;
    border-right: none;
    border-bottom: 3px solid #444;
    margin-top: 59px;
  }

  .sidebar a {
    display: inline-block;
    margin: 0 10px;
    padding: 10px;
    white-space: nowrap;
  }

  .btn-logout {
    display: none; /* Hide logout on small screens or reposition it elsewhere */
  }

  .container.content {
    margin-left: 0;
    margin-top: 20px;
    padding: 20px 10px;
  }

  .contact-form {
    padding: 20px;
    margin-top: 20px;
    max-width: 100%;
    box-sizing: border-box;
  }

  .contact-form h3 {
    font-size: 22px;
  }

  .contact-form button {
    font-size: 14px;
  }

  .alert {
    max-width: 100%;
    padding: 10px;
  }
}

  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <a class="navbar-brand" href="dashboard.php">
      <img src="/gnlproject/img/logo.png" alt="Logo">
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ml-auto">
      </ul>
    </div>
  </nav>
  <div class="sidebar">
    <a href="architect_index.php">Upload MRF</a>
    <a href="uploaded_mrf.php" style="position: relative; display: inline-block;">
  Submitted MRF
  <span class="badge badge-primary" style="
    position: absolute;
    top: 0;
    right: 0;
    transform: translate(50%, -50%);
    font-size: 12px;
    padding: 8px 8px;
    border-radius: 50%;
  ">
    <?php echo $totalSubmitted; ?>
  </span>
</a>
    <a href="contact.php">Contact</a>
    <a href="contact.php">Support</a>
    <button onclick="window.location.href='logout.php'" class="btn-logout">Logout</button>
</div>


<div class="container content">
  <?php if ($successMsg): ?>
    <div class="alert alert-success" role="alert"><?= htmlspecialchars($successMsg) ?></div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($errorMsg) ?></div>
  <?php endif; ?>

  <div class="contact-form">
    <h3>Submit Concern</h3>
    <form action="" method="POST" enctype="multipart/form-data" novalidate>
      <div class="form-group">
        <label for="employee_name">Your Name</label>
        <input
          type="text"
          class="form-control"
          name="employee_name"
          id="employee_name"
          required
          value="<?= htmlspecialchars($employee_name ?? '') ?>"
        >
      </div>
      <div class="form-group">
        <label for="project_name">Project Name</label>
        <input
          type="text"
          class="form-control"
          name="project_name"
          id="project_name"
          required
          value="<?= htmlspecialchars($project_name ?? '') ?>"
        >
      </div>
      <div class="form-group">
        <label for="concern">Concern / Message</label>
        <textarea
          class="form-control"
          name="concern"
          id="concern"
          rows="5"
          required><?= htmlspecialchars($concern ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label for="image_path">Upload Image (optional)</label>
        <input
          type="file"
          class="form-control-file"
          name="image_path"
          id="image_path"
          accept=".jpg,.jpeg,.png,.gif,.bmp"
        >
      </div>
      <button type="submit">Submit</button>
    </form>
  </div>
</div>

<script>
     document.addEventListener('DOMContentLoaded', function () {
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.sidebar');

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function () {
      sidebar.classList.toggle('collapsed');
    });
  }
});
    </script>
</body>
</html>

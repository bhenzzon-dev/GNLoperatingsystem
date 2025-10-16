<?php
session_start();

// Redirect if admin is already logged in
if (
    isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true &&
    isset($_SESSION["role"]) && $_SESSION["role"] === "admin"
) {
    header("location: admin_index.php");
    exit;
}


require_once "db_connect.php";

$username = $password = "";
$username_err = $password_err = $login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter your username.";
    } else {
        $username = trim($_POST["username"]);
    }

    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    if (empty($username_err) && empty($password_err)) {
        $sql = "SELECT id, username, password FROM admins WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $username, $hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            session_regenerate_id();
                            $_SESSION["admin_loggedin"] = true;
                            $_SESSION["admin_id"] = $id;
                            $_SESSION["admin_username"] = $username;
                            $_SESSION["role"] = "admin"; // important
                            header("location: admin_index.php");
                            exit;
                        } else {
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    $login_err = "Invalid username or password.";
                }
            } else {
                echo "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Pie EXPRESS</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">
    <!-- Our Custom CSS -->
    <link rel="stylesheet">
    <!-- Font Awesome JS -->
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Background */
        body {
            background: url('/gnlproject/img/bg.jpg') no-repeat center center fixed;
            background-size: cover;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Glassmorphism Card */
        .login-container {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .login-container h2 {
            font-family: 'Poppins', sans-serif;
            font-weight: bold;
            color: #fff;
        }

        /* Form Styling */
        .form-group label {
            color: #fff;
            font-weight: bold;
        }

        .form-control {
            border-radius: 25px;
            padding: 12px;
        }

        .btn-login {
            background: linear-gradient(45deg, #AE8625, #F7EF8A );
            color: #fff;
            padding: 12px;
            border-radius: 25px;
            width: 100%;
            font-weight: bold;
            transition: 0.3s;
        }

        .btn-login:hover {
            background: linear-gradient(45deg, #926f34, #dfbd69);
        }

        /* Error Messages */
        .alert {
            font-size: 14px;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .login-container {
                padding: 30px;
                width: 90%;
            }
        }
        .back-link {
    color: #926f34;
    font-size: 14px;
    text-decoration: none;
    transition: color 0.2s ease;
}

.back-link:hover {
    color: #dddddd;
    text-decoration: underline;
}
    </style>
</head>
<body>
    <div class="login-container">
    <img src="/gnlproject/img/logo.png" alt="Logo" style="width: 200px; align:top;" class="rounded-pill">
        <p class="text-light">Please enter your credentials</p>

        <?php if (!empty($login_err)): ?>
            <div class="alert alert-danger"><?php echo $login_err; ?></div>
        <?php endif; ?>

        <form action="admin_login.php" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>">
                <span class="invalid-feedback"><?php echo $username_err; ?></span>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                <span class="invalid-feedback"><?php echo $password_err; ?></span>
            </div>

            <div class="form-group">
                <input type="submit" class="btn btn-login" value="Login">
            </div>
        </form>
        <div class="text-center mt-3">
    <a href="/gnlproject/index.html" class="back-link">
        ← Back to Home Page
    </a>
</div>
    </div>
    <?php if (!empty($login_err)): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: '<?php echo $login_err; ?>',
                confirmButtonColor: 'red'
            });
        </script>
    <?php endif; ?>
</body>
</html>

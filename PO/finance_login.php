<?php
session_start();

// Redirect if the finance officer is already logged in
if (isset($_SESSION["finance_loggedin"]) && $_SESSION["finance_loggedin"] === true) {
    header("location: finance_index.php");
    exit;
}

require_once "db_connect.php";

$username = $password = "";
$username_err = $password_err = $login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter your username.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Check input errors before querying the database
    if (empty($username_err) && empty($password_err)) {
        // Prepare the SQL query to select the user from the finance table
        $sql = "SELECT id, username, password FROM finance WHERE username = ?";

        if ($stmt = $conn->prepare($sql)) {
            // Bind the parameters to the SQL query
            $stmt->bind_param("s", $param_username);
            $param_username = $username;

            // Execute the query
            if ($stmt->execute()) {
                $stmt->store_result();

                // Check if the username exists in the database
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $username, $hashed_password);
                    if ($stmt->fetch()) {
                        // Verify the password
                        if (password_verify($password, $hashed_password)) {
                            // Start a new session and regenerate the session ID to prevent session fixation attacks
                            session_regenerate_id();

                            // Store session variables
                            $_SESSION["finance_loggedin"] = true;
                            $_SESSION["finance_id"] = $id;
                            $_SESSION["finance_username"] = $username;
                            $_SESSION["role"] = "finance"; // Store the role as 'finance'

                            // Redirect to the finance dashboard (finance_index.php)
                            header("location: finance_index.php");
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

            // Close the statement
            $stmt->close();
        }
    }

    // Close the database connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Purchasing Officer</title>
    <link rel="icon" type="image/x-icon" href="/gnlproject/img/logo.png">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: url('/gnlproject/img/bg.jpg') no-repeat center center fixed;
            background-size: cover;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

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

        .alert {
            font-size: 14px;
        }

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

        <form action="finance_login.php" method="post">
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

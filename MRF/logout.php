<?php
session_start();

// Only unset the architect session variables
unset($_SESSION["architect_loggedin"]);
unset($_SESSION["architect_id"]);
unset($_SESSION["architect_username"]);

// Redirect to login page
header("location: architect_login.php");
exit;
?>

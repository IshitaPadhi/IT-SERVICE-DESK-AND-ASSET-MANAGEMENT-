<?php
include("db_connect.php");
session_start();

if(isset($_POST['login'])){

    $username = $_POST['username'];
    $password = $_POST['password'];

    $q = oci_parse($conn,"
        SELECT user_id, username, role, emp_id
        FROM USERS
        WHERE USERNAME = :u AND PASSWORD = :p
    ");

    oci_bind_by_name($q, ":u", $username);
    oci_bind_by_name($q, ":p", $password);
    oci_execute($q);
    $user = oci_fetch_assoc($q);

    if($user){
        $_SESSION['user_id'] = $user['USER_ID'];
        $_SESSION['username'] = $user['USERNAME'];
        $_SESSION['role'] = $user['ROLE'];
        $_SESSION['emp_id'] = $user['EMP_ID'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>IT Service Desk — Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="style.css" rel="stylesheet">
</head>
<body class="login-page">

<div class="login-card">

  <div class="login-logo">
    <div class="logo-icon"><i class="bi bi-headset"></i></div>
    <div>
      <div class="logo-name">IT Service Desk</div>
      <div class="logo-sub">Asset & Ticket Management</div>
    </div>
  </div>

  <div class="login-heading">Welcome back</div>
  <div class="login-sub">Sign in to your workspace</div>

  <?php if(isset($error)): ?>
  <div class="alert alert-danger">
    <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
  </div>
  <?php endif; ?>

  <form method="POST">

    <div class="mb-3">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
    </div>

    <div class="mb-4">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" placeholder="Enter password" required>
    </div>

    <button name="login" class="btn btn-primary btn-full">
      <i class="bi bi-box-arrow-in-right"></i> Sign In
    </button>

  </form>

</div>

</body>
</html>
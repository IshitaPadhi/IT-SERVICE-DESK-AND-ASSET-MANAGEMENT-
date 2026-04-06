<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];
$current_page = basename($_SERVER['PHP_SELF']);

// Avatar initials
$initials = strtoupper(substr($username, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>IT Service Desk</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="style.css" rel="stylesheet">

</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">

  <div class="sidebar-brand">
    <div class="brand-icon"><i class="bi bi-headset"></i></div>
    <div>
      <div class="brand-name">IT Service Desk</div>
      <div class="brand-sub">Asset & Ticket Management</div>
    </div>
  </div>

  <nav class="sidebar-nav">

    <!-- DASHBOARD -->
    <a href="dashboard.php" class="<?php echo ($current_page=='dashboard.php')?'active':''; ?>">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>

    <!-- TICKETS -->
    <div class="sidebar-section-label">Tickets</div>

    <a href="view_tickets.php" class="<?php echo ($current_page=='view_tickets.php')?'active':''; ?>">
      <i class="bi bi-ticket-detailed"></i> View Tickets
    </a>

    <?php if($role != 'ENGINEER'): ?>
    <a href="create_ticket.php" class="<?php echo ($current_page=='create_ticket.php')?'active':''; ?>">
      <i class="bi bi-plus-square"></i> Create Ticket
    </a>
    <?php endif; ?>


    <!-- ASSETS (ADMIN + ENGINEER) -->
    <?php if($role == 'ADMIN' || $role == 'ENGINEER'): ?>

    <div class="sidebar-section-label">Assets</div>

    <a href="view_assets.php" class="<?php echo ($current_page=='view_assets.php')?'active':''; ?>">
      <i class="bi bi-pc-display"></i> Assets
    </a>

    <a href="view_allocations.php" class="<?php echo ($current_page=='view_allocations.php')?'active':''; ?>">
      <i class="bi bi-list-check"></i> Allocations
    </a>

    <?php endif; ?>


    <!-- ADMIN ONLY -->
    <?php if($role == 'ADMIN'): ?>

    <a href="allocate_asset.php" class="<?php echo ($current_page=='allocate_asset.php')?'active':''; ?>">
      <i class="bi bi-person-check"></i> Allocate Asset
    </a>

    <div class="sidebar-section-label">Reports</div>

    <a href="reports.php" class="<?php echo ($current_page=='reports.php')?'active':''; ?>">
      <i class="bi bi-bar-chart-line"></i> SLA Report
    </a>

    <?php endif; ?>

  </nav>

</div>
<!-- END SIDEBAR -->


<!-- MAIN CONTENT -->
<div class="main-content">

  <!-- TOPBAR -->
  <div class="topbar">

    <div class="topbar-title">IT Service Desk</div>

    <div class="topbar-right">

      <!-- SEARCH -->
      <form method="GET" action="dashboard.php" style="display:flex;">
        <div class="search-box">
          <i class="bi bi-search"></i>
          <input 
            type="text" 
            name="q" 
            placeholder="Search tickets..."
            value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
        </div>
      </form>

      <!-- NOTIFICATIONS -->
      <div class="topbar-icon-btn">
        <i class="bi bi-bell" style="font-size:15px;"></i>
      </div>

      <!-- USER INFO -->
      <div class="user-chip">
        <div class="user-avatar"><?php echo $initials; ?></div>
        <div>
          <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
          <div class="user-role"><?php echo $role; ?></div>
        </div>
      </div>

      <!-- LOGOUT -->
      <a href="logout.php" class="btn-logout">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>

    </div>

  </div>
  <!-- END TOPBAR -->

  <!-- PAGE CONTENT -->
  <div class="content">
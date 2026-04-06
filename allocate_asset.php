<?php
include("layout.php");
include("db_connect.php");

$form_error = $form_success = null;

if(isset($_POST['allocate'])){

    $asset_id = $_POST['asset_id'];
    $emp_id   = $_POST['emp_id'];

    $check = "SELECT COUNT(*) AS CNT FROM asset_allocation WHERE asset_id=:asset_id AND status='ACTIVE'";
    $cstid = oci_parse($conn, $check);
    oci_bind_by_name($cstid, ":asset_id", $asset_id);
    oci_execute($cstid);
    $row = oci_fetch_assoc($cstid);

    if($row['CNT'] > 0){
        $form_error = "This asset is already allocated to someone.";
    } else {

        $query = "INSERT INTO asset_allocation
                  (allocation_id, asset_id, emp_id, allocated_date, status)
                  VALUES (
                    (SELECT NVL(MAX(allocation_id),0)+1 FROM asset_allocation),
                    :asset_id, :emp_id, SYSDATE, 'ACTIVE'
                  )";
        $stid = oci_parse($conn, $query);
        oci_bind_by_name($stid, ":asset_id", $asset_id);
        oci_bind_by_name($stid, ":emp_id",   $emp_id);
        oci_execute($stid);

        $update = "UPDATE asset SET status='IN_USE', assigned_to=:emp_id WHERE asset_id=:asset_id";
        $stid2 = oci_parse($conn, $update);
        oci_bind_by_name($stid2, ":emp_id",   $emp_id);
        oci_bind_by_name($stid2, ":asset_id", $asset_id);
        oci_execute($stid2);

        $form_success = "Asset allocated successfully.";
    }
}
?>

<div class="breadcrumb-nav">
  <a href="dashboard.php">Dashboard</a>
  <i class="bi bi-chevron-right"></i>
  <span>Allocate Asset</span>
</div>

<div class="page-header">
  <h3>Allocate Asset</h3>
  <p>Assign an available asset to an employee</p>
</div>

<?php if($form_error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo $form_error; ?></div>
<?php endif; ?>
<?php if($form_success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $form_success; ?></div>
<?php endif; ?>

<div class="it-card" style="max-width:560px; padding:28px;">
  <form method="POST">

    <div class="mb-3">
      <label class="form-label">Asset <span style="color:#FF5630;">*</span></label>
      <select name="asset_id" class="form-select" required>
        <option value="">-- Select available asset --</option>
        <?php
        $q = "SELECT asset_id, asset_name, asset_type FROM asset WHERE status='AVAILABLE' ORDER BY asset_name";
        $s = oci_parse($conn, $q); oci_execute($s);
        while($row = oci_fetch_assoc($s)){
            echo "<option value='{$row['ASSET_ID']}'>{$row['ASSET_NAME']} &nbsp;·&nbsp; {$row['ASSET_TYPE']}</option>";
        }
        ?>
      </select>
    </div>

    <div class="mb-4">
      <label class="form-label">Employee <span style="color:#FF5630;">*</span></label>
      <select name="emp_id" class="form-select" required>
        <option value="">-- Select employee --</option>
        <?php
        $q = "SELECT emp_id, emp_name FROM employee ORDER BY emp_name";
        $s = oci_parse($conn, $q); oci_execute($s);
        while($row = oci_fetch_assoc($s)){
            echo "<option value='{$row['EMP_ID']}'>" . htmlspecialchars($row['EMP_NAME']) . "</option>";
        }
        ?>
      </select>
    </div>

    <div style="display:flex; gap:10px;">
      <button type="submit" name="allocate" class="btn btn-primary">
        <i class="bi bi-person-check"></i> Allocate Asset
      </button>
      <a href="view_allocations.php" class="btn btn-secondary">View Allocations</a>
    </div>

  </form>
</div>

<?php include("layout_footer.php"); ?>
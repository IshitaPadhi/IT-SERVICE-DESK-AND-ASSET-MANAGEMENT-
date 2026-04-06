<?php
include("layout.php");
include("db_connect.php");

if(isset($_POST['submit'])){

    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority    = $_POST['priority'];
    $raised_by   = $_POST['raised_by'];

    if(empty($title) || empty($priority) || empty($raised_by)){
        $form_error = "Please fill in all required fields.";
    } else {

        if($priority == 'HIGH')        $sla_hours = 4;
        elseif($priority == 'MEDIUM')  $sla_hours = 8;
        else                           $sla_hours = 24;

        $id_query = oci_parse($conn, "SELECT NVL(MAX(TICKET_ID),0)+1 AS NEW_ID FROM TICKET");
        oci_execute($id_query);
        $row = oci_fetch_assoc($id_query);
        $ticket_id = $row['NEW_ID'];

        // offset_days = 0 → use SYSDATE
        $offset_days = 5;
        $created_at  = "SYSDATE + $offset_days";

        $query = "INSERT INTO TICKET
            (TICKET_ID, TITLE, DESCRIPTION, PRIORITY, STATUS, CREATED_AT, RAISED_BY, SLA_HOURS)
            VALUES (:id, :title, :description, :priority, 'OPEN', $created_at, :raised_by, :sla)";

        $stid = oci_parse($conn, $query);
        oci_bind_by_name($stid, ":id",          $ticket_id);
        oci_bind_by_name($stid, ":title",        $title);
        oci_bind_by_name($stid, ":description",  $description);
        oci_bind_by_name($stid, ":priority",     $priority);
        oci_bind_by_name($stid, ":raised_by",    $raised_by);
        oci_bind_by_name($stid, ":sla",          $sla_hours);

        $result = @oci_execute($stid, OCI_COMMIT_ON_SUCCESS);

        if($result){
            $form_success = "Ticket #$ticket_id created successfully.";
            echo "<script>setTimeout(()=>{ window.location.href='dashboard.php'; }, 1200);</script>";
        } else {
            $e = oci_error($stid);
            if(isset($e['message']) && strpos($e['message'], 'ORA-20001') !== false){
                $form_error = "Tickets cannot be created on weekends.";
            } else {
                $form_error = "Database error: " . ($e['message'] ?? 'Unknown error');
            }
        }
    }
}
?>

<div class="breadcrumb-nav">
  <a href="dashboard.php">Dashboard</a>
  <i class="bi bi-chevron-right"></i>
  <span>Create Ticket</span>
</div>

<div class="page-header">
  <h3>Create New Ticket</h3>
  <p>Submit a new support request to the IT help desk</p>
</div>

<?php if(isset($form_error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo $form_error; ?></div>
<?php endif; ?>
<?php if(isset($form_success)): ?>
<div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $form_success; ?></div>
<?php endif; ?>

<div class="it-card" style="max-width:600px; padding:28px;">

  <form method="POST">

    <div class="mb-3">
      <label class="form-label">Title <span style="color:#FF5630;">*</span></label>
      <input type="text" name="title" class="form-control"
        placeholder="Brief description of the issue" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="4"
        placeholder="Provide additional context or steps to reproduce..."></textarea>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;" class="mb-4">

      <div>
        <label class="form-label">Priority <span style="color:#FF5630;">*</span></label>
        <select name="priority" class="form-select" required>
          <option value="">Select priority</option>
          <option value="HIGH">🔴 HIGH — 4 hr SLA</option>
          <option value="MEDIUM">🟡 MEDIUM — 8 hr SLA</option>
          <option value="LOW">⚪ LOW — 24 hr SLA</option>
        </select>
      </div>

      <div>
        <label class="form-label">Raised By <span style="color:#FF5630;">*</span></label>
        <select name="raised_by" class="form-select" required>
          <option value="">Select employee</option>
          <?php
          $emp_query = oci_parse($conn, "SELECT EMP_ID, EMP_NAME FROM EMPLOYEE ORDER BY EMP_NAME");
          oci_execute($emp_query);
          while($emp = oci_fetch_assoc($emp_query)){
              echo "<option value='{$emp['EMP_ID']}'>" . htmlspecialchars($emp['EMP_NAME']) . "</option>";
          }
          ?>
        </select>
      </div>

    </div>

    <div style="display:flex; gap:10px; align-items:center;">
      <button type="submit" name="submit" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Create Ticket
      </button>
      <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
    </div>

  </form>

</div>

<?php include("layout_footer.php"); ?>
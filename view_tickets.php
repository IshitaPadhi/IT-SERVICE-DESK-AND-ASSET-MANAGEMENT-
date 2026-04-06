<?php
include("layout.php");
include("db_connect.php");

$emp_id = $_SESSION['emp_id'];
$role   = $_SESSION['role'];

$showToast = false;

// AUTO ASSIGN
if(isset($_POST['assign_ticket']) && $role == 'ADMIN'){
    $tid = intval($_POST['ticket_id']);

    $q = oci_parse($conn,"
        SELECT assigned_to, COUNT(*) AS CNT FROM ticket
        WHERE assigned_to IS NOT NULL AND status != 'CLOSED'
        GROUP BY assigned_to ORDER BY CNT ASC FETCH FIRST 1 ROW ONLY
    ");
    oci_execute($q);
    $row_eng = oci_fetch_assoc($q);

    if($row_eng){
        $engineer = $row_eng['ASSIGNED_TO'];
    } else {
        $q2 = oci_parse($conn,"SELECT emp_id FROM employee FETCH FIRST 1 ROW ONLY");
        oci_execute($q2);
        $engineer = oci_fetch_assoc($q2)['EMP_ID'];
    }

    $u = oci_parse($conn,"UPDATE ticket SET assigned_to=:eng, status='IN_PROGRESS' WHERE ticket_id=:tid");
    oci_bind_by_name($u, ":eng", $engineer);
    oci_bind_by_name($u, ":tid", $tid);
    oci_execute($u);
    oci_commit($conn);
    $showToast = true;
}
?>

<!-- REMOVE AUTO REFRESH (important) -->
<!-- <meta http-equiv="refresh" content="30"> -->

<div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start;">
  <div>
    <h3>Tickets</h3>
    <p>Browse and manage all support tickets</p>
  </div>
  <?php if($role != 'ENGINEER'): ?>
  <a href="create_ticket.php" class="btn btn-primary">
    <i class="bi bi-plus"></i> New Ticket
  </a>
  <?php endif; ?>
</div>

<!-- FILTER BAR -->
<div class="filter-bar">
  <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; width:100%;">

    <input type="text" name="ticket_id" class="form-control"
      style="width:120px;" placeholder="Ticket ID"
      value="<?php echo htmlspecialchars($_GET['ticket_id'] ?? ''); ?>">

    <select name="status" class="form-select" style="width:150px;">
      <option value="">All Statuses</option>
      <option <?php if(($_GET['status']??'')=='OPEN') echo 'selected'; ?> value="OPEN">Open</option>
      <option <?php if(($_GET['status']??'')=='IN_PROGRESS') echo 'selected'; ?> value="IN_PROGRESS">In Progress</option>
      <option <?php if(($_GET['status']??'')=='CLOSED') echo 'selected'; ?> value="CLOSED">Closed</option>
    </select>

    <select name="priority" class="form-select" style="width:150px;">
      <option value="">All Priorities</option>
      <option <?php if(($_GET['priority']??'')=='HIGH') echo 'selected'; ?> value="HIGH">High</option>
      <option <?php if(($_GET['priority']??'')=='MEDIUM') echo 'selected'; ?> value="MEDIUM">Medium</option>
      <option <?php if(($_GET['priority']??'')=='LOW') echo 'selected'; ?> value="LOW">Low</option>
    </select>

    <button class="btn btn-primary btn-sm">
      <i class="bi bi-funnel"></i> Filter
    </button>

    <?php if(!empty($_GET['ticket_id']) || !empty($_GET['status']) || !empty($_GET['priority'])): ?>
    <a href="view_tickets.php" class="btn btn-secondary btn-sm">
      <i class="bi bi-x"></i> Clear
    </a>
    <?php endif; ?>

  </form>
</div>

<!-- TABLE -->
<div class="it-card">
  <table class="it-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Raised By</th>
        <th>Assigned To</th>
        <th>Status</th>
        <th>Priority</th>
        <th style="text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody>

<?php
$query = "
SELECT
    t.ticket_id,
    t.title,

    -- ✅ SUBQUERY FIX (CORRECT NAME FETCH)
    (SELECT emp_name FROM employee WHERE emp_id = t.raised_by) AS raised_by_name,

    NVL(
        (SELECT emp_name FROM employee WHERE emp_id = t.assigned_to),
        'Unassigned'
    ) AS assigned_to_name,

    t.status,
    t.priority

FROM ticket t
WHERE 1=1
";

if($role=='ENGINEER') $query .= " AND t.assigned_to = :emp_id";
elseif($role=='USER')  $query .= " AND t.raised_by   = :emp_id";

if(!empty($_GET['ticket_id'])) $query .= " AND t.ticket_id = :ticket_id";
if(!empty($_GET['status']))    $query .= " AND t.status    = :status";
if(!empty($_GET['priority']))  $query .= " AND t.priority  = :priority";

$query .= " ORDER BY t.ticket_id DESC";

$stid = oci_parse($conn, $query);

if($role=='ENGINEER' || $role=='USER') oci_bind_by_name($stid, ":emp_id", $emp_id);
if(!empty($_GET['ticket_id'])){ $tid=intval($_GET['ticket_id']); oci_bind_by_name($stid,":ticket_id",$tid); }
if(!empty($_GET['status']))   oci_bind_by_name($stid,":status",$_GET['status']);
if(!empty($_GET['priority'])) oci_bind_by_name($stid,":priority",$_GET['priority']);

oci_execute($stid);

$hasData = false;
while($row = oci_fetch_assoc($stid)){
    $hasData = true;
    $tid = $row['TICKET_ID'];
    $s   = $row['STATUS'];
    $p   = $row['PRIORITY'];

    if($s=='OPEN')        $sbadge = "<span class='status-badge status-open'>Open</span>";
    elseif($s=='IN_PROGRESS') $sbadge = "<span class='status-badge status-progress'>In Progress</span>";
    else                  $sbadge = "<span class='status-badge status-closed'>Closed</span>";

    $pc = strtolower($p);
    $pbadge = "<span class='priority-chip priority-$pc'>$p</span>";

    $assigned = htmlspecialchars($row['ASSIGNED_TO_NAME']);
    $assigned_class = ($row['ASSIGNED_TO_NAME']=='Unassigned') ? 'text-muted text-sm' : '';

    echo "<tr onclick=\"window.location='ticket_details.php?id=$tid'\">";
    echo "<td><span class='ticket-id-mono'>#$tid</span></td>";
    echo "<td class='ticket-title-cell'>" . htmlspecialchars($row['TITLE']) . "</td>";
    echo "<td style='font-size:13px;'>" . htmlspecialchars($row['RAISED_BY_NAME']) . "</td>";
    echo "<td style='font-size:13px;' class='$assigned_class'>$assigned</td>";
    echo "<td>$sbadge</td>";
    echo "<td>$pbadge</td>";
    echo "<td onclick='event.stopPropagation()' style='text-align:right;'>";
    echo "<a href='ticket_details.php?id=$tid' class='btn btn-outline-primary btn-sm me-1'>View</a>";

    if($role=='ADMIN' && $row['ASSIGNED_TO_NAME']=='Unassigned'){
        echo "<form method='POST' style='display:inline;'>
                <input type='hidden' name='ticket_id' value='$tid'>
                <button type='submit' name='assign_ticket' class='btn btn-success btn-sm'>Auto Assign</button>
              </form>";
    }

    echo "</td></tr>";
}

if(!$hasData){
    echo "<tr><td colspan='7'>
        <div class='empty-state'>
          <i class='bi bi-ticket'></i>
          <p>No tickets found matching your filters.</p>
        </div>
    </td></tr>";
}
?>

    </tbody>
  </table>
</div>

<?php if($showToast): ?>
<div class="it-toast"><i class="bi bi-check-circle"></i> Ticket assigned successfully</div>
<?php endif; ?>

<?php include("layout_footer.php"); ?>
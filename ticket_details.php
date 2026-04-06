<?php
include("layout.php");
include("db_connect.php");

$user_id = $_SESSION['user_id'];
$emp_id  = $_SESSION['emp_id'];
$role    = $_SESSION['role'];

if(!isset($_GET['id'])){
    echo "<div class='alert alert-danger'><i class='bi bi-exclamation-circle'></i> No Ticket ID provided.</div>";
    include("layout_footer.php");
    exit();
}

$ticket_id = intval($_GET['id']);

$query = "
    SELECT t.*, e.emp_name,
           e2.emp_name AS assigned_name,
           (SYSDATE - t.created_at)*24 AS HOURS_ELAPSED
    FROM ticket t
    JOIN employee e ON t.raised_by = e.emp_id
    LEFT JOIN employee e2 ON t.assigned_to = e2.emp_id
    WHERE t.ticket_id = :id
";

if($role=='ENGINEER') $query .= " AND t.assigned_to = :emp_id_filter";
elseif($role=='USER')  $query .= " AND t.raised_by   = :emp_id_filter";

$stid = oci_parse($conn, $query);
oci_bind_by_name($stid, ":id", $ticket_id);
if($role=='ENGINEER' || $role=='USER') oci_bind_by_name($stid, ":emp_id_filter", $emp_id);
oci_execute($stid);
$row = oci_fetch_assoc($stid);

if(!$row){
    echo "<div class='alert alert-danger'><i class='bi bi-shield-x'></i> Access denied or ticket not found.</div>";
    include("layout_footer.php");
    exit();
}

// CLOSE TICKET
if(isset($_POST['close_ticket']) && $role != 'USER'){
    $u = oci_parse($conn,"UPDATE ticket SET status='CLOSED' WHERE ticket_id=:id");
    oci_bind_by_name($u, ":id", $ticket_id);
    oci_execute($u);
    oci_commit($conn);
    echo "<script>window.location.href='ticket_details.php?id=$ticket_id';</script>";
    exit();
}

// ADD COMMENT
if(isset($_POST['add_comment'])){
    $comment = trim($_POST['comment']);
    if(!empty($comment)){
        $cid_q = oci_parse($conn,"SELECT NVL(MAX(COMMENT_ID),0)+1 AS CID FROM TICKET_COMMENTS");
        oci_execute($cid_q);
        $cid = oci_fetch_assoc($cid_q)['CID'];

        $ins = oci_parse($conn,"
            INSERT INTO TICKET_COMMENTS (COMMENT_ID, TICKET_ID, USER_ID, COMMENT_TEXT, COMMENT_DATE)
            VALUES (:c1, :c2, :c3, :c4, SYSDATE)
        ");
        oci_bind_by_name($ins, ":c1", $cid);
        oci_bind_by_name($ins, ":c2", $ticket_id);
        oci_bind_by_name($ins, ":c3", $emp_id);
        oci_bind_by_name($ins, ":c4", $comment);
        oci_execute($ins);
        oci_commit($conn);
        echo "<script>window.location.href='ticket_details.php?id=$ticket_id';</script>";
        exit();
    }
}

// SLA logic
$sla_hours = $row['SLA_HOURS'] ?? 24;
$elapsed   = max($row['HOURS_ELAPSED'], 0);

if($row['STATUS']=='CLOSED'){
    $sla_status = "MET"; $sla_class = "sla-met";
} elseif($elapsed < $sla_hours * 0.5){
    $sla_status = "SAFE"; $sla_class = "sla-safe";
} elseif($elapsed < $sla_hours){
    $sla_status = "WARNING"; $sla_class = "sla-warning";
} else {
    $sla_status = "BREACHED"; $sla_class = "sla-breach";
}

$sla_pct = min(100, ($sla_hours > 0 ? ($elapsed/$sla_hours)*100 : 0));
$fill_class = ($sla_class=='sla-safe') ? 'fill-safe' : (($sla_class=='sla-warning') ? 'fill-warning' : 'fill-breach');

$s = $row['STATUS'];
if($s=='OPEN')        $sbadge = "<span class='status-badge status-open'>Open</span>";
elseif($s=='IN_PROGRESS') $sbadge = "<span class='status-badge status-progress'>In Progress</span>";
else                  $sbadge = "<span class='status-badge status-closed'>Closed</span>";

$p = $row['PRIORITY'];
$pc = strtolower($p);
$pbadge = "<span class='priority-chip priority-$pc'>$p</span>";
?>

<div class="breadcrumb-nav">
  <a href="dashboard.php">Dashboard</a>
  <i class="bi bi-chevron-right"></i>
  <a href="view_tickets.php">Tickets</a>
  <i class="bi bi-chevron-right"></i>
  <span>#<?php echo $row['TICKET_ID']; ?></span>
</div>

<!-- HEADER ROW -->
<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
  <div>
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
      <span style="font-family:'DM Mono',monospace; font-size:12px; color:var(--text-muted);">#<?php echo $row['TICKET_ID']; ?></span>
      <?php echo $sbadge; ?>
      <?php echo $pbadge; ?>
    </div>
    <h3 style="font-size:20px; font-weight:700; color:var(--text-primary); letter-spacing:-0.3px; margin:0;">
      <?php echo htmlspecialchars($row['TITLE']); ?>
    </h3>
  </div>

  <?php if($row['STATUS'] != 'CLOSED' && $role != 'USER'): ?>
  <form method="POST">
    <button name="close_ticket" class="btn btn-danger" onclick="return confirm('Close this ticket?');">
      <i class="bi bi-check-lg"></i> Close Ticket
    </button>
  </form>
  <?php endif; ?>
</div>

<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px; align-items:start;">

  <!-- LEFT COLUMN -->
  <div>

    <!-- Description -->
    <div class="it-card p-4 mb-4">
      <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted); margin-bottom:10px;">Description</div>
      <p style="font-size:13.5px; color:var(--text-secondary); line-height:1.6; margin:0;">
        <?php echo $row['DESCRIPTION'] ? htmlspecialchars($row['DESCRIPTION']) : '<em style="color:var(--text-muted);">No description provided.</em>'; ?>
      </p>
    </div>

    <!-- Activity Timeline -->
    <div class="it-card p-4 mb-4">
      <div style="font-size:13px; font-weight:700; color:var(--text-primary); margin-bottom:16px; display:flex; align-items:center; gap:8px;">
        <i class="bi bi-clock-history" style="color:var(--brand-primary);"></i> Activity Timeline
      </div>

      <div class="timeline">
      <?php
      $act_q = oci_parse($conn,"
          SELECT action_type, action_date, comments
          FROM ticket_activity WHERE ticket_id=:id ORDER BY action_date DESC
      ");
      oci_bind_by_name($act_q,":id",$ticket_id);
      oci_execute($act_q);
      $hasActivity = false;
      while($act = oci_fetch_assoc($act_q)){
          $hasActivity = true;
          echo "<div class='timeline-item'>
            <div class='timeline-dot'><i class='bi bi-activity'></i></div>
            <div>
              <div class='timeline-action'>" . htmlspecialchars($act['ACTION_TYPE']) . "</div>
              <div class='timeline-date'>" . date("d M Y, H:i", strtotime($act['ACTION_DATE'])) . "</div>
              <div class='timeline-comment'>" . htmlspecialchars($act['COMMENTS']) . "</div>
            </div>
          </div>";
      }
      if(!$hasActivity){
          echo "<div style='color:var(--text-muted); font-size:13px;'>No activity recorded yet.</div>";
      }
      ?>
      </div>
    </div>

    <!-- Comments -->
    <div class="it-card p-4">
      <div style="font-size:13px; font-weight:700; color:var(--text-primary); margin-bottom:14px; display:flex; align-items:center; gap:8px;">
        <i class="bi bi-chat-text" style="color:var(--brand-primary);"></i> Comments
      </div>

      <?php
      $cq = oci_parse($conn,"
          SELECT c.comment_text, c.comment_date, 'User '||c.user_id AS emp_name
          FROM ticket_comments c WHERE c.ticket_id=:id ORDER BY c.comment_date DESC
      ");
      oci_bind_by_name($cq,":id",$ticket_id);
      oci_execute($cq);
      $hasComments = false;
      while($c = oci_fetch_assoc($cq)){
          $hasComments = true;
          $initials = strtoupper(substr($c['EMP_NAME'],0,1));
          echo "<div class='comment-item'>
            <div class='comment-avatar'>$initials</div>
            <div>
              <span class='comment-author'>" . htmlspecialchars($c['EMP_NAME']) . "</span>
              <span class='comment-date'>" . date("d M Y, H:i", strtotime($c['COMMENT_DATE'])) . "</span>
              <div class='comment-text'>" . htmlspecialchars($c['COMMENT_TEXT']) . "</div>
            </div>
          </div>";
      }
      if(!$hasComments) echo "<div style='color:var(--text-muted); font-size:13px; margin-bottom:14px;'>No comments yet.</div>";
      ?>

      <form method="POST" style="margin-top:14px; border-top:1px solid var(--border-light); padding-top:14px;">
        <textarea name="comment" class="form-control mb-2" rows="3"
          placeholder="Add a comment..." required></textarea>
        <button name="add_comment" class="btn btn-primary btn-sm">
          <i class="bi bi-send"></i> Post Comment
        </button>
      </form>

    </div>

  </div>

  <!-- RIGHT COLUMN — Meta -->
  <div>

    <div class="it-card p-4 mb-4">
      <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted); margin-bottom:14px;">Ticket Details</div>

      <div style="display:flex; flex-direction:column; gap:14px;">

        <div>
          <div class="meta-label">Status</div>
          <div class="meta-value"><?php echo $sbadge; ?></div>
        </div>

        <div>
          <div class="meta-label">Priority</div>
          <div class="meta-value"><?php echo $pbadge; ?></div>
        </div>

        <div>
          <div class="meta-label">Raised By</div>
          <div class="meta-value"><?php echo htmlspecialchars($row['EMP_NAME']); ?></div>
        </div>

        <div>
          <div class="meta-label">Assigned To</div>
          <div class="meta-value">
            <?php echo $row['ASSIGNED_NAME'] ? htmlspecialchars($row['ASSIGNED_NAME']) : '<span style="color:var(--text-muted);">Unassigned</span>'; ?>
          </div>
        </div>

        <div>
          <div class="meta-label">SLA Status</div>
          <div class="meta-value"><span class="sla-badge <?php echo $sla_class; ?>"><?php echo $sla_status; ?></span></div>
        </div>

        <div>
          <div class="meta-label">Time Used</div>
          <div class="sla-bar-wrap" style="margin-top:6px;">
            <div class="sla-bar">
              <div class="sla-bar-fill <?php echo $fill_class; ?>" style="width:<?php echo round($sla_pct); ?>%"></div>
            </div>
            <span class="sla-bar-label"><?php echo round($elapsed,1); ?>/<?php echo $sla_hours; ?>h</span>
          </div>
        </div>

      </div>
    </div>

    <a href="view_tickets.php" class="btn btn-secondary" style="width:100%; justify-content:center;">
      <i class="bi bi-arrow-left"></i> Back to Tickets
    </a>

  </div>
</div>

<?php include("layout_footer.php"); ?>
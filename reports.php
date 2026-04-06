<?php
include("layout.php");
include("db_connect.php");

$type = $_GET['type'] ?? 'all';

$query = "
SELECT
    ticket_id, title, sla_hours, status,
    ROUND((SYSDATE - created_at)*24, 2) AS elapsed_hours,
    ROUND((NVL(resolved_at, SYSDATE) - created_at)*24, 2) AS resolution_hours,
    CASE
        WHEN status='CLOSED' AND (NVL(resolved_at,SYSDATE)-created_at)*24 > sla_hours THEN 'BREACHED'
        WHEN status='CLOSED' THEN 'MET'
        WHEN (SYSDATE-created_at)*24 > sla_hours THEN 'BREACHED'
        WHEN (SYSDATE-created_at)*24 > sla_hours*0.5 THEN 'WARNING'
        ELSE 'SAFE'
    END AS SLA_STATUS
FROM TICKET
WHERE sla_hours IS NOT NULL
";

if($type=='breach'){
    $query .= " AND (
        (status='CLOSED' AND (NVL(resolved_at,SYSDATE)-created_at)*24 > sla_hours)
        OR (status!='CLOSED' AND (SYSDATE-created_at)*24 > sla_hours)
    )";
} elseif($type=='warning'){
    $query .= " AND status!='CLOSED'
        AND (SYSDATE-created_at)*24 BETWEEN sla_hours*0.5 AND sla_hours";
}

$query .= " ORDER BY created_at DESC";
$stid = oci_parse($conn, $query);
oci_execute($stid);
?>

<div class="page-header">
  <?php if($type=='breach'): ?>
    <h3>SLA Breached Tickets</h3>
    <p>Tickets that have exceeded their SLA window</p>
  <?php elseif($type=='warning'): ?>
    <h3>SLA Warning Tickets</h3>
    <p>Tickets approaching their SLA limit</p>
  <?php else: ?>
    <h3>SLA Report</h3>
    <p>Full SLA compliance overview across all tickets</p>
  <?php endif; ?>
</div>

<!-- TYPE TABS -->
<div style="display:flex; gap:6px; margin-bottom:20px; border-bottom:1px solid var(--border-light); padding-bottom:0;">
  <?php
  $tabs = [
    ['all',     'All Tickets',    ''],
    ['breach',  'Breached',       'color:#AE2A19;'],
    ['warning', 'Warning',        'color:#7F5F01;'],
  ];
  foreach($tabs as $tab):
    $active = ($type==$tab[0]);
    echo "<a href='reports.php?type={$tab[0]}' style='
      font-size:13px; font-weight:600; padding:8px 14px; text-decoration:none;
      border-bottom: 2px solid " . ($active ? "var(--brand-primary)" : "transparent") . ";
      color: " . ($active ? "var(--brand-primary)" : "var(--text-secondary)") . ";
      {$tab[2]}
      margin-bottom:-1px; transition:color 0.12s;
    '>{$tab[1]}</a>";
  endforeach;
  ?>
</div>

<div class="it-card">
  <table class="it-table">
    <thead>
      <tr>
        <th>Ticket ID</th>
        <th>Title</th>
        <th>Status</th>
        <th>SLA (hrs)</th>
        <th>Time Used</th>
        <th>SLA Status</th>
        <th>Progress</th>
      </tr>
    </thead>
    <tbody>

<?php
$hasData = false;
while($row = oci_fetch_assoc($stid)){
    $hasData = true;
    $s = $row['STATUS'];
    $sla_status = $row['SLA_STATUS'];
    $sla_hours  = $row['SLA_HOURS'];

    // Time used
    $time_used = ($s=='CLOSED') ? $row['RESOLUTION_HOURS'] : $row['ELAPSED_HOURS'];

    // SLA badge
    $sla_pct = min(100, ($sla_hours > 0 ? ($time_used/$sla_hours)*100 : 0));
    if($sla_status=='BREACHED'){ $sbadge="<span class='sla-badge sla-breach'>Breached</span>"; $fc='fill-breach'; }
    elseif($sla_status=='WARNING'){ $sbadge="<span class='sla-badge sla-warning'>Warning</span>"; $fc='fill-warning'; }
    elseif($sla_status=='SAFE'){    $sbadge="<span class='sla-badge sla-safe'>Safe</span>"; $fc='fill-safe'; }
    else{                           $sbadge="<span class='sla-badge sla-met'>Met</span>"; $fc='fill-safe'; }

    // Status badge
    if($s=='OPEN') $stbadge="<span class='status-badge status-open'>Open</span>";
    elseif($s=='IN_PROGRESS') $stbadge="<span class='status-badge status-progress'>In Progress</span>";
    else $stbadge="<span class='status-badge status-closed'>Closed</span>";

    echo "<tr onclick=\"window.location='ticket_details.php?id={$row['TICKET_ID']}'\">";
    echo "<td><span class='ticket-id-mono'>#{$row['TICKET_ID']}</span></td>";
    echo "<td class='ticket-title-cell'>" . htmlspecialchars($row['TITLE']) . "</td>";
    echo "<td>$stbadge</td>";
    echo "<td style='font-family:DM Mono,monospace; font-size:12.5px;'>{$sla_hours}h</td>";
    echo "<td style='font-family:DM Mono,monospace; font-size:12.5px;'>{$time_used}h</td>";
    echo "<td>$sbadge</td>";
    echo "<td style='min-width:100px;'>
        <div class='sla-bar'>
          <div class='sla-bar-fill $fc' style='width:" . round($sla_pct) . "%'></div>
        </div>
    </td>";
    echo "</tr>";
}

if(!$hasData){
    echo "<tr><td colspan='7'>
        <div class='empty-state'>
          <i class='bi bi-bar-chart'></i>
          <p>No tickets match this filter.</p>
        </div>
    </td></tr>";
}
?>

    </tbody>
  </table>
</div>

<div style="margin-top:16px;">
  <a href="dashboard.php" class="btn btn-secondary">
    <i class="bi bi-arrow-left"></i> Back to Dashboard
  </a>
</div>

<?php include("layout_footer.php"); ?>
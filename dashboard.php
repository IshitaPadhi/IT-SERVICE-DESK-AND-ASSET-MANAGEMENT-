<?php
include("layout.php");
include("db_connect.php");

/* =========================
   SEARCH
========================= */
$search_results = [];
if(isset($_GET['search']) && !empty($_GET['search'])){
    $search = '%' . strtoupper($_GET['search']) . '%';
    $search_q = oci_parse($conn,"
        SELECT t.ticket_id, t.title, t.status, t.priority, e.emp_name
        FROM ticket t
        JOIN employee e ON t.raised_by = e.emp_id
        WHERE UPPER(t.title) LIKE :search
           OR TO_CHAR(t.ticket_id) LIKE :search
           OR UPPER(e.emp_name) LIKE :search
        ORDER BY t.ticket_id DESC
        FETCH FIRST 10 ROWS ONLY
    ");
    oci_bind_by_name($search_q, ":search", $search);
    oci_execute($search_q);
    while($row = oci_fetch_assoc($search_q)){ $search_results[] = $row; }
}

/* =========================
   METRICS
========================= */
$q1 = oci_parse($conn,"SELECT COUNT(*) AS C FROM TICKET"); oci_execute($q1);
$total = oci_fetch_assoc($q1)['C'];

$q2 = oci_parse($conn,"SELECT COUNT(*) AS C FROM TICKET WHERE STATUS='OPEN'"); oci_execute($q2);
$open = oci_fetch_assoc($q2)['C'];

$q3 = oci_parse($conn,"SELECT COUNT(*) AS C FROM TICKET WHERE STATUS='CLOSED'"); oci_execute($q3);
$closed = oci_fetch_assoc($q3)['C'];

$q4 = oci_parse($conn,"SELECT COUNT(*) AS C FROM TICKET WHERE STATUS='IN_PROGRESS'"); oci_execute($q4);
$progress = oci_fetch_assoc($q4)['C'];

/* SLA */
$sla_safe = $sla_warning = $sla_breach = 0;
$sla_q = oci_parse($conn,"
    SELECT SLA_HOURS, (SYSDATE - CREATED_AT)*24 AS ELAPSED
    FROM TICKET WHERE STATUS != 'CLOSED' AND SLA_HOURS IS NOT NULL
");
oci_execute($sla_q);
while($r = oci_fetch_assoc($sla_q)){
    $sla = $r['SLA_HOURS']; $elapsed = $r['ELAPSED'];
    if($elapsed < $sla * 0.5) $sla_safe++;
    elseif($elapsed < $sla) $sla_warning++;
    else $sla_breach++;
}

$sla_alert_q = oci_parse($conn,"
    SELECT
        NVL(SUM(CASE WHEN (SYSDATE - created_at)*24 > sla_hours THEN 1 ELSE 0 END),0) AS BREACHED,
        NVL(SUM(CASE WHEN (SYSDATE - created_at)*24 BETWEEN sla_hours*0.5 AND sla_hours THEN 1 ELSE 0 END),0) AS WARNING
    FROM ticket WHERE status != 'CLOSED' AND sla_hours IS NOT NULL
");
oci_execute($sla_alert_q);
$sla_alert = oci_fetch_assoc($sla_alert_q);
$breached = $sla_alert['BREACHED'];
$warning  = $sla_alert['WARNING'];

/* Priority */
$high = $medium = $low = 0;
$pq = oci_parse($conn,"SELECT PRIORITY, COUNT(*) AS CNT FROM TICKET GROUP BY PRIORITY");
oci_execute($pq);
while($p = oci_fetch_assoc($pq)){
    if($p['PRIORITY']=='HIGH') $high = $p['CNT'];
    if($p['PRIORITY']=='MEDIUM') $medium = $p['CNT'];
    if($p['PRIORITY']=='LOW') $low = $p['CNT'];
}
?>

<!-- PAGE HEADER -->
<div class="page-header">
  <h3>Dashboard</h3>
  <p>Real-time ticket monitoring &amp; SLA insights</p>
</div>

<!-- SEARCH BAR -->
<form method="GET" class="mb-4">
  <div class="search-box" style="max-width:460px; background:white; padding:8px 14px;">
    <i class="bi bi-search"></i>
    <input type="text" name="search" style="width:100%; font-size:13.5px;"
      placeholder="Search by Ticket ID, Title, or Employee..."
      value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
    <button type="submit" class="btn btn-primary btn-sm" style="flex-shrink:0;">Search</button>
  </div>
</form>

<!-- SEARCH RESULTS -->
<?php if(!empty($search_results)): ?>
<div class="it-card mb-4">
  <div style="padding:16px 20px; border-bottom:1px solid var(--border-light); display:flex; justify-content:space-between; align-items:center;">
    <span style="font-size:13px; font-weight:600;">Search Results</span>
    <span style="font-size:12px; color:var(--text-muted);"><?php echo count($search_results); ?> found</span>
  </div>
  <table class="it-table">
    <thead>
      <tr>
        <th>ID</th><th>Title</th><th>Employee</th><th>Status</th><th>Priority</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($search_results as $r): ?>
      <tr onclick="window.location='ticket_details.php?id=<?php echo $r['TICKET_ID']; ?>'">
        <td><span class="ticket-id-mono">#<?php echo $r['TICKET_ID']; ?></span></td>
        <td class="ticket-title-cell"><?php echo htmlspecialchars($r['TITLE']); ?></td>
        <td><?php echo htmlspecialchars($r['EMP_NAME']); ?></td>
        <td>
          <?php
          $s = $r['STATUS'];
          if($s=='OPEN') echo "<span class='status-badge status-open'>Open</span>";
          elseif($s=='IN_PROGRESS') echo "<span class='status-badge status-progress'>In Progress</span>";
          else echo "<span class='status-badge status-closed'>Closed</span>";
          ?>
        </td>
        <td>
          <?php
          $pr = $r['PRIORITY'];
          $pc = strtolower($pr);
          echo "<span class='priority-chip priority-$pc'>$pr</span>";
          ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- SLA ALERT CARDS -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">

  <div class="metric-card metric-breach">
    <div class="metric-icon" style="background:#FFEBE6; color:#AE2A19; font-size:17px;">
      <i class="bi bi-exclamation-triangle"></i>
    </div>
    <div class="metric-label">SLA Breached</div>
    <div class="metric-value"><?php echo $breached; ?></div>
    <div class="metric-sub">tickets exceeded SLA</div>
  </div>

  <div class="metric-card metric-warning-card">
    <div class="metric-icon" style="background:#FFF7D6; color:#7F5F01; font-size:17px;">
      <i class="bi bi-clock-history"></i>
    </div>
    <div class="metric-label">SLA Warning</div>
    <div class="metric-value"><?php echo $warning; ?></div>
    <div class="metric-sub">approaching SLA limit</div>
  </div>

</div>

<!-- TICKET METRICS -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px;">

  <div class="metric-card metric-total">
    <div class="metric-icon"><i class="bi bi-ticket"></i></div>
    <div class="metric-label">Total</div>
    <div class="metric-value"><?php echo $total; ?></div>
  </div>

  <div class="metric-card metric-open">
    <div class="metric-icon"><i class="bi bi-circle"></i></div>
    <div class="metric-label">Open</div>
    <div class="metric-value"><?php echo $open; ?></div>
  </div>

  <div class="metric-card metric-progress">
    <div class="metric-icon"><i class="bi bi-arrow-repeat"></i></div>
    <div class="metric-label">In Progress</div>
    <div class="metric-value"><?php echo $progress; ?></div>
  </div>

  <div class="metric-card metric-closed">
    <div class="metric-icon"><i class="bi bi-check-circle"></i></div>
    <div class="metric-label">Closed</div>
    <div class="metric-value"><?php echo $closed; ?></div>
  </div>

</div>

<!-- CHARTS -->
<div class="charts-grid">

  <div class="chart-card">
    <div class="chart-title"><i class="bi bi-pie-chart" style="margin-right:6px; color:var(--brand-primary);"></i>SLA Status Distribution</div>
    <canvas id="slaChart" height="220"></canvas>
  </div>

  <div class="chart-card">
    <div class="chart-title"><i class="bi bi-bar-chart" style="margin-right:6px; color:var(--brand-primary);"></i>Tickets by Priority</div>
    <canvas id="priorityChart" height="220"></canvas>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartDefaults = {
  font: { family: "'DM Sans', sans-serif", size: 12 },
  color: '#5E6C84'
};
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#5E6C84';

new Chart(document.getElementById('slaChart'), {
  type: 'doughnut',
  data: {
    labels: ['Safe', 'Warning', 'Breached'],
    datasets: [{
      data: [<?php echo $sla_safe; ?>, <?php echo $sla_warning; ?>, <?php echo $sla_breach; ?>],

      // 🔥 SOFT / OPAQUE COLORS
      backgroundColor: [
        'rgba(16, 185, 129, 0.25)',   // soft green
        'rgba(245, 158, 11, 0.25)',   // soft amber
        'rgba(239, 68, 68, 0.25)'     // soft red
      ],

      // subtle borders (clean definition)
      borderColor: [
        'rgba(16, 185, 129, 0.6)',
        'rgba(245, 158, 11, 0.6)',
        'rgba(239, 68, 68, 0.6)'
      ],

      borderWidth: 1.2,
      hoverOffset: 3
    }]
  },

  options: {
    cutout: '75%', // thinner ring → premium look

    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          padding: 14,
          boxWidth: 10,
          usePointStyle: true, // 🔥 rounded dots instead of squares
          pointStyle: 'circle',
          font: { size: 11 }
        }
      }
    }
  }
});

new Chart(document.getElementById('priorityChart'), {
  type: 'bar',
  data: {
    labels: ['High', 'Medium', 'Low'],
    datasets: [{
      data: [<?php echo $high; ?>, <?php echo $medium; ?>, <?php echo $low; ?>],
      backgroundColor: ['#FFEBE6', '#FFF7D6', '#F1F2F4'],
      borderColor: ['#FF5630', '#FFC400', '#8590A2'],
      borderWidth: 2,
      borderRadius: 6
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false } },
      y: { grid: { color: '#EBECF0' }, beginAtZero: true, ticks: { precision: 0 } }
    }
  }
});
</script>

<?php include("layout_footer.php"); ?>
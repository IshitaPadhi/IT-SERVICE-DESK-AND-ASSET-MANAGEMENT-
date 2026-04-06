<?php
include("layout.php");
include("db_connect.php");

$query = "
    SELECT a.asset_id, a.asset_name, a.asset_type, a.status, e.emp_name
    FROM asset a
    LEFT JOIN employee e ON a.assigned_to = e.emp_id
    ORDER BY a.asset_id DESC
";
$stid = oci_parse($conn, $query);
oci_execute($stid);
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start;">
  <div>
    <h3>Assets</h3>
    <p>Inventory of all IT hardware and equipment</p>
  </div>
  <a href="allocate_asset.php" class="btn btn-primary">
    <i class="bi bi-person-check"></i> Allocate Asset
  </a>
</div>

<div class="it-card">
  <table class="it-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Asset Name</th>
        <th>Type</th>
        <th>Status</th>
        <th>Assigned To</th>
      </tr>
    </thead>
    <tbody>

<?php
$hasData = false;
while($row = oci_fetch_assoc($stid)){
    $hasData = true;
    $s = $row['STATUS'];

    // Status styling
    if($s=='AVAILABLE'){
        $badge = "<span class='status-badge status-closed'>Available</span>";
    } elseif($s=='IN_USE'){
        $badge = "<span class='status-badge status-progress'>In Use</span>";
    } elseif($s=='UNDER_MAINTENANCE'){
        $badge = "<span class='sla-badge sla-warning'>Maintenance</span>";
    } else {
        $badge = "<span class='status-badge'>" . htmlspecialchars($s) . "</span>";
    }

    $assigned = $row['EMP_NAME'] ? htmlspecialchars($row['EMP_NAME']) : '<span style="color:var(--text-muted);">Unassigned</span>';

    echo "<tr>";
    echo "<td><span style='font-family:DM Mono,monospace; font-size:12px; color:var(--text-muted);'>#{$row['ASSET_ID']}</span></td>";
    echo "<td style='font-weight:500;'>" . htmlspecialchars($row['ASSET_NAME']) . "</td>";
    echo "<td style='font-size:12.5px; color:var(--text-secondary);'>" . htmlspecialchars($row['ASSET_TYPE']) . "</td>";
    echo "<td>$badge</td>";
    echo "<td style='font-size:13px;'>$assigned</td>";
    echo "</tr>";
}

if(!$hasData){
    echo "<tr><td colspan='5'>
        <div class='empty-state'>
          <i class='bi bi-pc-display'></i>
          <p>No assets found in inventory.</p>
        </div>
    </td></tr>";
}
?>

    </tbody>
  </table>
</div>

<?php include("layout_footer.php"); ?>
<?php
include("layout.php");
include("db_connect.php");

$query = "
    SELECT a.allocation_id, e.emp_name, s.asset_name, s.asset_type,
           a.allocated_date, a.status
    FROM asset_allocation a
    JOIN employee e ON a.emp_id   = e.emp_id
    JOIN asset    s ON a.asset_id = s.asset_id
    ORDER BY a.allocated_date DESC
";
$stid = oci_parse($conn, $query);
oci_execute($stid);
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start;">
  <div>
    <h3>Asset Allocations</h3>
    <p>Track all asset assignments and returns</p>
  </div>
  <a href="allocate_asset.php" class="btn btn-primary">
    <i class="bi bi-plus"></i> New Allocation
  </a>
</div>

<div class="it-card">
  <table class="it-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Employee</th>
        <th>Asset</th>
        <th>Type</th>
        <th>Date Allocated</th>
        <th>Status</th>
        <th style="text-align:right;">Action</th>
      </tr>
    </thead>
    <tbody>

<?php
$hasData = false;
while($row = oci_fetch_assoc($stid)){
    $hasData = true;
    $status = strtoupper(trim($row['STATUS']));
    $date   = date("d M Y", strtotime($row['ALLOCATED_DATE']));

    if($status=='ACTIVE' || $status=='ALLOCATED'){
        $badge = "<span class='status-badge status-closed'>Active</span>";
        $can_return = true;
    } else {
        $badge = "<span class='status-badge status-open' style='background:#F1F2F4; color:#44546F;'>Returned</span>";
        $can_return = false;
    }

    echo "<tr>";
    echo "<td><span style='font-family:DM Mono,monospace; font-size:12px; color:var(--text-muted);'>#{$row['ALLOCATION_ID']}</span></td>";
    echo "<td style='font-size:13.5px; font-weight:500;'>" . htmlspecialchars($row['EMP_NAME']) . "</td>";
    echo "<td style='font-size:13.5px;'>" . htmlspecialchars($row['ASSET_NAME']) . "</td>";
    echo "<td style='font-size:12.5px; color:var(--text-secondary);'>" . htmlspecialchars($row['ASSET_TYPE']) . "</td>";
    echo "<td style='font-size:12.5px; color:var(--text-secondary);'>$date</td>";
    echo "<td>$badge</td>";
    echo "<td style='text-align:right;'>";

    if($can_return){
        echo "<a href='return_asset.php?id={$row['ALLOCATION_ID']}'
                onclick=\"return confirm('Return this asset?');\"
                class='btn btn-danger btn-sm'>
                <i class='bi bi-arrow-return-left'></i> Return
              </a>";
    } else {
        echo "<span style='font-size:12px; color:var(--text-muted);'>Completed</span>";
    }

    echo "</td></tr>";
}

if(!$hasData){
    echo "<tr><td colspan='7'>
        <div class='empty-state'>
          <i class='bi bi-inbox'></i>
          <p>No allocations found.</p>
        </div>
    </td></tr>";
}
?>

    </tbody>
  </table>
</div>

<?php include("layout_footer.php"); ?>
<?php
include("db_connect.php");

$id = $_GET['id'];

$q1 = "UPDATE asset_allocation SET status='RETURNED', return_date=SYSDATE WHERE allocation_id=:id";
$s1 = oci_parse($conn, $q1);
oci_bind_by_name($s1, ":id", $id);
oci_execute($s1);

// Also update asset status back to AVAILABLE
$q2 = "UPDATE asset SET status='AVAILABLE', assigned_to=NULL
       WHERE asset_id = (SELECT asset_id FROM asset_allocation WHERE allocation_id=:id)";
$s2 = oci_parse($conn, $q2);
oci_bind_by_name($s2, ":id", $id);
oci_execute($s2);

oci_commit($conn);
header("Location: view_allocations.php");
exit();
<?php
// close_ticket.php
$conn = oci_connect("IT_DESK", "itdesk123", "localhost/XEPDB1");
if(isset($_GET['id'])){
    $id = $_GET['id'];
    $query = "UPDATE TICKET SET STATUS='CLOSED', RESOLVED_AT=SYSDATE WHERE TICKET_ID=:id";
    $stid = oci_parse($conn, $query);
    oci_bind_by_name($stid, ":id", $id);
    oci_execute($stid);
    header("Location: ticket_details.php?id=".$id);
}
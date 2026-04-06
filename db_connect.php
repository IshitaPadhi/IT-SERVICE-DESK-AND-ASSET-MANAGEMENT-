<?php
$conn = oci_connect("IT_DESK", "itdesk123", "localhost/XEPDB1");
if(!$conn){
    $e = oci_error();
    die("Connection failed: " . $e['message']);
}
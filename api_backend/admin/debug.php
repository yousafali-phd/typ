<?php
require_once("../../api_backend/mysqli.php");
$q = $mysqli->query("SELECT * FROM admins");
$rows = [];
if($q) {
  while($row = $q->fetch_assoc()) $rows[] = $row;
}
echo json_encode(["admins" => $rows]);

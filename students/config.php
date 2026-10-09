<?php
$h = "sql300.infinityfree.com";
$u = "if0_42938533";
$p = "rheadumanat30";
$db = "if0_42938533_XXX";
$con = mysqli_connect($h,$u,$p,$db);
if (!$con){
    die("could not connect to database". mysqli_connect_error());
}
?>
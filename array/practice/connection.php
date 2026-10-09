<?php
$host = "localhost";
$username = "root";
$password = "";
$db = "practice";

$conn = new mysqli($host, $username, $password, $db);

if (!$conn){
    die("could not connect to your database". mysqli_connect_error());
}
?>
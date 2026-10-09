<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'product_db';

$conn = mysqli_connect($host,$username,$password,$database);

if (!$conn){
    die("could not connect to your database" .mysqli_connect_error());
}
?>
<?php
include("connection.php");
if($_SERVER["REQUEST_METHOD"]== "POST"){
    $n = $_POST['sname'];
    $e = $_POST['semail'];
    $a = $_POST['saddress'];

$sql = ("INSERT INTO practice(name,email,address)values('$n','$e','$a')");
    if(mysqli_query($conn,$sql)){
        echo "new student added";
    }else{
        echo "student failed to add";
    }
}

?>
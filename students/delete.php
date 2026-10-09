<?php
include('config.php');
$id = $_GET['id'];
$sql = mysqli_query($con,"DELETE FROM information where id = '$id'");
if ($sql==true) {
    echo "data all deleted under" .'<a href="index.php"> view students </a>';
}else{
    echo "data not deleted";
}

?>
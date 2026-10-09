<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Students</title>
</head>
<body>
    <form method="POST">
        <table>
            <tr>
                <td><label>Enter Fullname</label></td>
                <td><input type="text" name="fname"></td>
</tr>
 <tr>
                <td><label>Enter Email address</label></td>
                <td><input type="email"name="email"></td>
</tr>
<td><label>Select Gender</label>
<td><select name="gender">
    <option value="">--Select Gender</option>
    <option value="female">female</option>
    <option value -="male">male</option>
</select>
</td>
</tr>
<td></td>
<td><input type="submit" name="submit"></td>
</tr>
</table>
<?php
include('config.php');
if ($_SERVER['REQUEST_METHOD']=="POST"){
   $fname = $_POST['fname'];
    $email = $_POST['email'];
     $gender =$_POST['gender'];
$sql = mysqli_query($con, "INSERT INTO information(name,email,gender)VALUES('$fname','$email','$gender')");
if ($sql ==true){
    echo "data saved". '<a href="index.php"> view students </a>';
}else{
    echo "data not saved".mysql_error($sql);
}
}
?>
</form>
</body>
</html>
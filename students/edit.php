<?php
include('config.php');
$id = $_GET['id'];
$sql = mysqli_query($con,"SELECT id,name,email,gender from information where id = '$id'");
while ($row = mysqli_fetch_assoc($sql)) {
    ?>
    <!DOCTYPE html>
    <head>
        <title>Update date</title>
    </head>
    <body>
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo $row['id']?>">
            <table>
                <tr>
                    <td><label>Fullname</label></td>
                    <td><input type="text" name="fname" value="<?php echo $row['name']?>"></td>
                    </tr>
                    <tr>
                    <td><label>Email Address</label></td>
                    <td><input type="email" name="email" value="<?php echo $row['email']?>"></td>
                    </tr>
                    <td><label>Gender</label>
                    <td><select name="gender">
                        <option value="gender">
                            <option value="" disabled><?php echo $row['gender']?></option>
                            <option value="female">female</option>
                            <option value="male">male</option>
</select>
</td>
</tr>
<td></td>
<td><input type="submit" name="submit"></td>
</tr>
<?php }?>
<?php
include('config.php');
if ($_SERVER['REQUEST_METHOD']=="POST"){
    $id =$_POST['id'];
    $fname =$_POST['fname'];
    $email =$_POST['email'];
    $gender =$_POST['gender'];
    $sql = mysqli_query($con,"UPDATE information SET name='$fname', email='$email', gender='$gender' WHERE id = '$id'");
    if ($sql == true){
        echo "data update". '<a href="index.php"> view students </a>';;
    }else{
        echo "data not update".mysqli_error($sql);
    }
}
?>
    </table>
</form>
    </body>
    </html>

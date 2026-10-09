<?php
include('config.php');

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


$sql = mysqli_query($con,"SELECT * FROM information");
?>
<!DOCTYPE html>
<html>
<head>
    <title></title>
</head>
<body>

<h2>Dashboard</h2>

<p>Welcome User, <?php echo $_SESSION['name']; ?>!</p>

<p>
    Username:
    <?php echo $_SESSION['username']; ?>
</p>

<a href="logout.php" >Logout</a><br>

    <a href="save.php"> Add Students </a>
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Gender</th>
            <th>Action</th>
</tr>
<?php while($row = mysqli_fetch_assoc($sql)){ ?>
    <tr>
        <td><?php echo $row['id'];?></td>
        <td><?php echo $row['name'];?></td>
        <td><?php echo $row['email'];?></td>
        <td><?php echo $row['gender'];?></td>
        <td><a href="edit.php?id=<?php echo $row['id'];?>">Edit </a> | <a href="delete.php?id=<?php echo $row['id'];?>">Delete</a></td>

</tr>
<?php }?>
</table>

</body>
</html>
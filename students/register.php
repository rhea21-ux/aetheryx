<?php
include "config.php";

if(isset($_POST['register'])) {

    $name = $_POST['name'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (name, username, password)
            VALUES (?, ?, ?)";
    
    $stmt = $con->prepare($sql);

    $stmt->bind_param(
        "sss",
        $name,
        $username,
        $hashedPassword
    );

    if ($stmt->execute()){
        echo "Registration successful.";
    } else {
        echo "Registration failed: " . $stmt->error;
    }
}
?>

<h2>Register</h2>

<form method="POST">

Name:
<input type="text" name="name" required>
<br><br>

Username:
<input type="text" name="username" required>
<br><br>

Password:
<input type="password" name="password" required>
<br><br>

<button type="submit" name="register">
    Register
</button>

</form>

<br>

<a href="login.php">Go to login</a>
<?php

session_start();

include "config.php";

if (isset($_POST['login'])) {

$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE username = ?";

$stmt = $con->prepare($sql);

$stmt->bind_param("s", $username);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 1) {

    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['username'] = $user['username'];

        header("Location: index.php");
        exit();

    }else {
        echo "Invalid password.";
        echo "<br><br>";
        echo '<a href="login.php">Back to Login</a>';

    }

} else {
    echo "username not found.";
     echo "<br><br>";
        echo '<a href="login.php">Back to Login</a>';
    }
    
}

?>

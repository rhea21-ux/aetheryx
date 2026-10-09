<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>

<h2>Login</h2>

<form action="auth.php" method="POST">



Username:
<input type="text" name="username" required>
<br><br>

Password:
<input type="password" name="password" required>
<br><br>

<button type="submit" name="login">
        Login
</button>

</form>

<br>

<a href="register.php">Create Account</a>

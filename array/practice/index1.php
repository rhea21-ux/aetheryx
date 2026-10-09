<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Information</title>
</head>
<body>
    <h1>STUDENT INFO</h1>

    <form action="mysql.php" method="POST">
        <table>
            <tr>
                <td><label>student name:</label></td>
                <td><input type="text" name="sname" placeholder="enter name"></td>
</tr>
            <tr>
                <td><label>email:</label></td>
                <td><input type="text" name="semail" placeholder="enter email"></td>
</tr>
            <tr>
                <td><label>address:</label></td>
                <td><input type="text" name="saddress" placeholder="enter address"></td>
</tr>
            <tr>
                <td><input type="submit" value="save"></td>
            </tr>
        </table>
</body>
</html>


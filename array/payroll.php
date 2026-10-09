<!DOCTYPE html>
<html>
<head>
 <title>Payroll Calculator</title>
</head>
<body>
    <h2>Payroll Calculator</h2>
    <form method="POST">

Employee Name:<br>
<input type="text" name="employee_name" required><br><br>

Hourly Rate:<br>
<input type="number" name="hourly_rate" steps="0.01" required><br><br>

Hourly Worked:<br>
<input type="number" name="hours_worked" required><br><br>
<input type="submit" name= "compute" value="Compute Salary">

    </form>
<hr>

<?php

function computeGross($rate, $hours){
    return $rate * $hours;

}
function computeTax($gross){
    return $gross * 0.10;
}
function computeNetSalary($gross, $tax){
    return $gross - $tax;
}

if (isset($_POST['compute'])) {
    
$employee = $_POST['employee_name'];
$rate = $_POST['hourly_rate'];
$hours = $_POST['hours_worked'];

$gross = computeGross($rate, $hours);
$tax = computeTax($gross);
$net = computeNetSalary($gross, $tax);

echo "<h3>Payroll Information</h3>";

echo "Employee Name: $employee , <br>";
echo "Hourly Rate ₱" , number_format($rate, 2) , "<br>";
echo "Hours Worked: $hours , <br><br>";

echo "<strong>Gross Salary:</strong> ₱" , number_format($gross, 2) , "<br>";
echo "<strong>Tax (10%):</strong> ₱" , number_format($tax, 2) , "<br>";
echo "<strong>Net Salary:</strong> ₱" , number_format($net, 2) , "<br>";
}
?>
</body>

</html>
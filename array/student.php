<!DOCTYPE html>
<html>
<head>
    <title>Student Evaluation</title>
    
</head>
<body>
    <?php
        $students = array(
            array("1", "John Doe", "25","75"),
            array("2", "Juan DelaCruz", "25","70"),
            array("3", "Jane Smith", "25","65"),
            array("4", "Mary Johnson", "25","85"),
            array("5", "James Brown", "25","90")
        );
    echo "<table border='1'>";
    echo "<h3>Student Evaluation System</h3>";
    echo "<tr>
          <th> Student #</th>
          <th> Full Name</th>
          <th> Age</th>
          <th> Grade</th>
          <th> Remarks</th>
        </tr>";

        foreach ($students as $students) {
            $id =  $students[0];
            $name =  $students[1];
            $age =  $students[2];
            $Grade =  $students[3];

            if ($Grade >= 75) {
                $remarks = "Passed";
            } else {
                $remarks = "Failed";
            }

            echo "<tr>
                    <td>$id</td>
                    <td>$name</td>
                    <td>$age</td>
                    <td>$Grade</td>
                    <td>$remarks</td>
                </tr>";
        }
        echo "</table>";
        
        ?>
</body>
</html>
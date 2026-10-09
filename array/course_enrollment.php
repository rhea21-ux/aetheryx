<!DOCTYPE html>
<html>
<head>
    <title>Course Enrollment</title>
    
</head>
<body>
    <?php
        $students = array(
            array("1", "John Doe", "BSIT","1","10"),
            array("2", "Juan DelaCruz", "BSIS","1","8"),
            array("3", "Jane Smith", "BSCS","1","9"),
            array("4", "Mary Johnson", "BSIT","1","7"),
            array("5", "James Brown", "BSIS","1","6")
        );
    echo "<table border='1'>";
    echo "<h3>Course Enrollment System</h3>";
    echo "<tr>
          <th> ID </th>
          <th> Full Name</th>
          <th> Course</th>
          <th> Year Level</th>
          <th> Enrollment Score</th>
          <th> Status </th>
        </tr>";

        foreach ($students as $students) {
            $id =  $students[0];
            $name =  $students[1];
            $Course =  $students[2];
            $YearLevel =  $students[3];
            $EnrollmentScore =  $students[4];
        

        if ($EnrollmentScore >= 75) {
                $Status = "QUALIFIED";
            } else {
                $Status = "NOT QUALIFIED";
            }
            

            echo "<tr>
                    <td>$id</td>
                    <td>$name</td>
                    <td>$Course</td>
                    <td>$YearLevel</td>
                    <td>$EnrollmentScore</td>
                    <td>$Status</td>

                </tr>";
        }
        echo "</table>";
        
        ?>
</body>
</html>
<!DOCTYPE html>
<html>
<head> 
    <title> Simp Cal</title>
</head>
<body>
    <form method="POST">
        <h3> CAL SYSTEM</h3>
        <input type ="number" name="num1" placeholder="Enter First Number" />
        <br>
        <input type ="number" name="num2" placeholder="Enter Second Number" />
        <br><br>
        <input type="submit" name="add" value="Add">
        <input type="submit" name="sub" value="Subtract">
        <input type="submit" name="mul" value="Multiply">
        <input type="submit" name="div" value="Divide">
    </form>
    <?php
    if($_SERVER['REQUEST_METHOD'] == "POST"){
        $num1 = $_POST['num1'];
        $num2 = $_POST['num2'];
      
        if(isset($_POST['add'])){
           echo "RESULT" , "<br>";
           echo "$num1 + $num2 =" , ($num1 + $num2);
        }else if(isset($_POST['sub'])){
            echo "RESULT" , "<br>";
            echo "$num1 - $num2 =" , ($num1 - $num2);
        }else if(isset($_POST['mul'])){
            echo "RESULT" , "<br>";
            echo "$num1 * $num2 =" , ($num1 * $num2);
        }else if(isset($_POST['div'])){
            if($num2 != 0){
            echo "RESULT" , "<br>";
            echo "$num1 / $num2 =" , ($num1 / $num2);
        }else{
        echo "Can't be divided by zero";
        }
           
        }
             
        
        
    }
    
    ?>

</body>
</html>
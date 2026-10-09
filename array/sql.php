 <?php
    include('connection.php');
    if($_SERVER["REQUEST_METHOD"]=="POST"){
        $pname= $_POST['pname'];
        $quantity= $_POST['quantity'];
        $desc= $_POST['desc'];
        $price = $_POST['price'] ;
        $sql = mysqli_query($conn,"INSERT INTO products(productname,quantity,description,price)VALUES('$pname','$quantity','$desc','$price')");
        if($sql == true){
            echo "Succesfully saved";
            
        }else{
            echo ("there was an error");
        }
    }
    ?>
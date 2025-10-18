<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Check if user has permission to access this page
if($_SESSION["role"] != "pembeli"){
    header("location: dashboard.php");
    exit;
}

// Include config file
require_once "config.php";

// Check if product id is provided
if(!isset($_GET["id"]) || empty($_GET["id"])){
    header("location: products.php");
    exit;
}

 $product_id = $_GET["id"];

// Get product data with stock information
 $sql = "SELECT p.*, s.quantity 
        FROM products p 
        JOIN stock s ON p.id = s.product_id 
        WHERE p.id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    if(mysqli_stmt_execute($stmt)){
        $result = mysqli_stmt_get_result($stmt);
        if(mysqli_num_rows($result) == 1){
            $product = mysqli_fetch_assoc($result);
        } else{
            header("location: products.php");
            exit;
        }
    } else{
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Check if product is in stock
if($product['quantity'] <= 0){
    header("location: products.php");
    exit;
}

// Define variables and initialize with empty values
 $quantity = 1;
 $quantity_err = "";
 $success_message = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Validate quantity
    if(empty(trim($_POST["quantity"]))){
        $quantity_err = "Please enter a quantity.";
    } else{
        $quantity = trim($_POST["quantity"]);
        
        // Check if quantity is valid
        if(!is_numeric($quantity) || $quantity <= 0){
            $quantity_err = "Please enter a valid quantity.";
        } elseif($quantity > $product['quantity']){
            $quantity_err = "Only " . $product['quantity'] . " items available in stock.";
        }
    }
    
    // Check input errors before processing
    if(empty($quantity_err)){
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Create customer if not exists (using user info)
            $customer_id = 0;
            $sql = "SELECT id FROM customers WHERE email = ?";
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "s", $param_email);
                $param_email = $_SESSION["username"] . "@example.com"; // Using username as email placeholder
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    if(mysqli_num_rows($result) == 1){
                        $row = mysqli_fetch_assoc($result);
                        $customer_id = $row['id'];
                    } else{
                        // Create new customer
                        $sql_insert = "INSERT INTO customers (name, address, phone, email) VALUES (?, '', '', ?)";
                        if($stmt_insert = mysqli_prepare($conn, $sql_insert)){
                            mysqli_stmt_bind_param($stmt_insert, "ss", $param_name, $param_email);
                            $param_name = $_SESSION["name"];
                            $param_email = $_SESSION["username"] . "@example.com";
                            mysqli_stmt_execute($stmt_insert);
                            $customer_id = mysqli_insert_id($conn);
                            mysqli_stmt_close($stmt_insert);
                        }
                    }
                }
                mysqli_stmt_close($stmt);
            }
            
            // Calculate total amount
            $total_amount = $product['price'] * $quantity;
            
            // Insert sale record
            $sql = "INSERT INTO sales (customer_id, user_id, total_amount) VALUES (?, ?, ?)";
            $sale_id = 0;
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "iid", $param_customer_id, $param_user_id, $param_total_amount);
                $param_customer_id = $customer_id;
                $param_user_id = $_SESSION["id"];
                $param_total_amount = $total_amount;
                mysqli_stmt_execute($stmt);
                $sale_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
            }
            
            // Insert sale detail record
            $sql = "INSERT INTO sale_details (sale_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "iiid", $param_sale_id, $param_product_id, $param_quantity, $param_unit_price);
                $param_sale_id = $sale_id;
                $param_product_id = $product_id;
                $param_quantity = $quantity;
                $param_unit_price = $product['price'];
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            
            // Update stock
            $sql = "UPDATE stock SET quantity = quantity - ? WHERE product_id = ?";
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "ii", $param_quantity, $param_product_id);
                $param_quantity = $quantity;
                $param_product_id = $product_id;
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Set success message
            $success_message = "Your order has been placed successfully!";
            
            // Refresh product data
            $sql = "SELECT p.*, s.quantity 
                    FROM products p 
                    JOIN stock s ON p.id = s.product_id 
                    WHERE p.id = ?";
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "i", $product_id);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    if(mysqli_num_rows($result) == 1){
                        $product = mysqli_fetch_assoc($result);
                    }
                }
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            echo "Error: " . $e->getMessage();
        }
    }
    
    // Close connection
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Product - TV Inventory System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" style="width: 250px;">
        <div class="text-center mb-4">
            <h3>TV Inventory</h3>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
            </li>
            <?php if($_SESSION["role"] == "admin" || $_SESSION["role"] == "karyawan"): ?>
            <li class="nav-item">
                <a class="nav-link" href="customers.php">
                    <i class="fas fa-users mr-2"></i> Customers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="suppliers.php">
                    <i class="fas fa-truck mr-2"></i> Suppliers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="products.php">
                    <i class="fas fa-tv mr-2"></i> Products
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="stock.php">
                    <i class="fas fa-boxes mr-2"></i> Stock
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="sales.php">
                    <i class="fas fa-shopping-cart mr-2"></i> Sales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="purchases.php">
                    <i class="fas fa-file-invoice-dollar mr-2"></i> Purchases
                </a>
            </li>
            <?php endif; ?>
            <?php if($_SESSION["role"] == "pembeli"): ?>
            <li class="nav-item">
                <a class="nav-link active" href="products.php">
                    <i class="fas fa-tv mr-2"></i> Products
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="my_orders.php">
                    <i class="fas fa-list-alt mr-2"></i> My Orders
                </a>
            </li>
            <?php endif; ?>
            <?php if($_SESSION["role"] == "admin"): ?>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-user-cog mr-2"></i> Users
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item mt-auto">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <span class="navbar-brand">Buy Product</span>
        <span class="navbar-text ml-auto">
            Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?> (<?php echo htmlspecialchars($_SESSION["role"]); ?>)
        </span>
    </nav>

    <!-- Main Content -->
    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Buy Product</h2>
            <a href="products.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to Products
            </a>
        </div>

        <?php if(!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <img src="https://via.placeholder.com/500x300?text=TV+Product" class="img-fluid product-image" alt="<?php echo $product['name']; ?>">
                        <h4><?php echo $product['name']; ?></h4>
                        <p><strong>Brand:</strong> <?php echo $product['brand']; ?></p>
                        <p><strong>Model:</strong> <?php echo $product['model']; ?></p>
                        <p><strong>Description:</strong> <?php echo $product['description']; ?></p>
                        <p><strong>Price:</strong> Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></p>
                        <p><strong>Stock:</strong> 
                            <?php 
                            if($product['quantity'] <= 5){
                                echo '<span class="stock-low">' . $product['quantity'] . ' left (Low Stock)</span>';
                            } else{
                                echo '<span class="stock-good">' . $product['quantity'] . ' available</span>';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Place Your Order</h6>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $product_id); ?>" method="post">
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="number" name="quantity" class="form-control <?php echo (!empty($quantity_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $quantity; ?>" min="1" max="<?php echo $product['quantity']; ?>">
                                <span class="invalid-feedback"><?php echo $quantity_err; ?></span>
                                <small class="form-text text-muted">Maximum quantity: <?php echo $product['quantity']; ?></small>
                            </div>
                            <div class="form-group">
                                <label>Total Price</label>
                                <input type="text" class="form-control" value="Rp <?php echo number_format($product['price'] * $quantity, 0, ',', '.'); ?>" readonly>                            </div>
                            <div class="form-group">
                                <input type="submit" class="btn btn-primary btn-block" value="Place Order">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Update total price when quantity changes
        document.querySelector('input[name="quantity"]').addEventListener('input', function() {
            const quantity = parseInt(this.value) || 0;
            const price = <?php echo $product['price']; ?>;
            const total = quantity * price;
            document.querySelector('input[readonly]').value = 'Rp ' + total.toLocaleString('id-ID');
        });
    </script>
</body>
</html>
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

// Check if order id is provided
if(!isset($_GET["id"]) || empty($_GET["id"])){
    header("location: my_orders.php");
    exit;
}

 $order_id = $_GET["id"];

// Get customer ID for the current user
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
        }
    }
    mysqli_stmt_close($stmt);
}

// Get sale data
 $sql = "SELECT s.id, s.sale_date, s.total_amount, u.name AS user_name 
        FROM sales s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ? AND s.customer_id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "ii", $order_id, $customer_id);
    if(mysqli_stmt_execute($stmt)){
        $result = mysqli_stmt_get_result($stmt);
        if(mysqli_num_rows($result) == 1){
            $order = mysqli_fetch_assoc($result);
        } else{
            header("location: my_orders.php");
            exit;
        }
    } else{
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Get sale details
 $order_details = [];
 $sql = "SELECT sd.id, sd.quantity, sd.unit_price, p.name AS product_name, p.brand, p.model 
        FROM sale_details sd 
        JOIN products p ON sd.product_id = p.id 
        WHERE sd.sale_id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    if(mysqli_stmt_execute($stmt)){
        $result = mysqli_stmt_get_result($stmt);
        while($row = mysqli_fetch_assoc($result)){
            $order_details[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View My Order - TV Inventory System</title>
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
                <a class="nav-link" href="products.php">
                    <i class="fas fa-tv mr-2"></i> Products
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="my_orders.php">
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
        <span class="navbar-brand">Order Details</span>
        <span class="navbar-text ml-auto">
            Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?> (<?php echo htmlspecialchars($_SESSION["role"]); ?>)
        </span>
    </nav>

    <!-- Main Content -->
    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Order #<?php echo $order['id']; ?></h2>
            <a href="my_orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to My Orders
            </a>
        </div>

        <div class="card">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Order Information</h6>
            </div>
            <div class="card-body">
                <p><strong>Order ID:</strong> <?php echo $order['id']; ?></p>
                <p><strong>Date:</strong> <?php echo date("Y-m-d H:i:s", strtotime($order['sale_date'])); ?></p>
                <p><strong>Salesperson:</strong> <?php echo $order['user_name']; ?></p>
                <p><strong>Total Amount:</strong> Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Order Details</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($order_details as $detail): ?>
                                <tr>
                                    <td><?php echo $detail['product_name']; ?></td>
                                    <td><?php echo $detail['brand']; ?></td>
                                    <td><?php echo $detail['model']; ?></td>
                                    <td><?php echo $detail['quantity']; ?></td>
                                    <td>Rp <?php echo number_format($detail['unit_price'], 0, ',', '.'); ?></td>
                                    <td>Rp <?php echo number_format($detail['quantity'] * $detail['unit_price'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-right"><strong>Total:</strong></td>
                                <td><strong>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></strong></td>                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
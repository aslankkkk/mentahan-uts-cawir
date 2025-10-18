<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Check if user has permission to access this page
if($_SESSION["role"] == "pembeli"){
    header("location: dashboard.php");
    exit;
}

// Include config file
require_once "config.php";

// Check if sale id is provided
if(!isset($_GET["id"]) || empty($_GET["id"])){
    header("location: sales.php");
    exit;
}

 $sale_id = $_GET["id"];

// Get sale data
 $sql = "SELECT s.id, s.sale_date, s.total_amount, c.name AS customer_name, c.address, c.phone, c.email, u.name AS user_name 
        FROM sales s 
        JOIN customers c ON s.customer_id = c.id 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $sale_id);
    if(mysqli_stmt_execute($stmt)){
        $result = mysqli_stmt_get_result($stmt);
        if(mysqli_num_rows($result) == 1){
            $sale = mysqli_fetch_assoc($result);
        } else{
            header("location: sales.php");
            exit;
        }
    } else{
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Get sale details
 $sale_details = [];
 $sql = "SELECT sd.id, sd.quantity, sd.unit_price, p.name AS product_name, p.brand, p.model 
        FROM sale_details sd 
        JOIN products p ON sd.product_id = p.id 
        WHERE sd.sale_id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $sale_id);
    if(mysqli_stmt_execute($stmt)){
        $result = mysqli_stmt_get_result($stmt);
        while($row = mysqli_fetch_assoc($result)){
            $sale_details[] = $row;
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
    <title>View Sale - TV Inventory System</title>
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
                <a class="nav-link active" href="sales.php">
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
        <span class="navbar-brand">Sale Details</span>
        <span class="navbar-text ml-auto">
            Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?> (<?php echo htmlspecialchars($_SESSION["role"]); ?>)
        </span>
    </nav>

    <!-- Main Content -->
    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Sale #<?php echo $sale['id']; ?></h2>
            <a href="sales.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to Sales
            </a>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Sale Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Sale ID:</strong> <?php echo $sale['id']; ?></p>
                        <p><strong>Date:</strong> <?php echo date("Y-m-d H:i:s", strtotime($sale['sale_date'])); ?></p>
                        <p><strong>Salesperson:</strong> <?php echo $sale['user_name']; ?></p>
                        <p><strong>Total Amount:</strong> Rp <?php echo number_format($sale['total_amount'], 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Customer Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong> <?php echo $sale['customer_name']; ?></p>
                        <p><strong>Address:</strong> <?php echo $sale['address']; ?></p>
                        <p><strong>Phone:</strong> <?php echo $sale['phone']; ?></p>
                        <p><strong>Email:</strong> <?php echo $sale['email']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Sale Details</h6>
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
                            <?php foreach($sale_details as $detail): ?>
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
                                <td><strong>Rp <?php echo number_format($sale['total_amount'], 0, ',', '.'); ?></strong></td>
                            </tr>
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
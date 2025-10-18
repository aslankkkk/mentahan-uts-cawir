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

// Add new sale
if(isset($_POST["add_sale"])){
    $customer_id = $_POST["customer_id"];
    $product_id = $_POST["product_id"];
    $quantity = $_POST["quantity"];
    
    // Get product price
    $sql = "SELECT price FROM products WHERE id = ?";
    $product_price = 0;
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if(mysqli_num_rows($result) == 1){
                $row = mysqli_fetch_assoc($result);
                $product_price = $row['price'];
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Calculate total amount
    $total_amount = $product_price * $quantity;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
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
            $param_unit_price = $product_price;
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
        
        // Redirect to sales page
        header("location: sales.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        echo "Error: " . $e->getMessage();
    }
}

// Delete sale
if(isset($_GET["delete"])){
    $id = $_GET["delete"];
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get sale details to restore stock
        $sql = "SELECT product_id, quantity FROM sale_details WHERE sale_id = ?";
        $sale_details = [];
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $id);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_assoc($result)){
                    $sale_details[] = $row;
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        // Restore stock for each product
        foreach($sale_details as $detail){
            $sql = "UPDATE stock SET quantity = quantity + ? WHERE product_id = ?";
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "ii", $param_quantity, $param_product_id);
                $param_quantity = $detail['quantity'];
                $param_product_id = $detail['product_id'];
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        
        // Delete sale details
        $sql = "DELETE FROM sale_details WHERE sale_id = ?";
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $param_id);
            $param_id = $id;
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // Delete sale
        $sql = "DELETE FROM sales WHERE id = ?";
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $param_id);
            $param_id = $id;
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Redirect to sales page
        header("location: sales.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        echo "Error: " . $e->getMessage();
    }
}

// Get sales data with customer and user information
 $sales = [];
 $sql = "SELECT s.id, s.sale_date, s.total_amount, c.name AS customer_name, u.name AS user_name 
        FROM sales s 
        JOIN customers c ON s.customer_id = c.id 
        JOIN users u ON s.user_id = u.id 
        ORDER BY s.sale_date DESC";
if($result = mysqli_query($conn, $sql)){
    while($row = mysqli_fetch_assoc($result)){
        $sales[] = $row;
    }
    mysqli_free_result($result);
}

// Get customers
 $customers = [];
 $sql = "SELECT id, name FROM customers ORDER BY name";
if($result = mysqli_query($conn, $sql)){
    while($row = mysqli_fetch_assoc($result)){
        $customers[] = $row;
    }
    mysqli_free_result($result);
}

// Get products with stock
 $products = [];
 $sql = "SELECT p.id, p.name, s.quantity 
        FROM products p 
        JOIN stock s ON p.id = s.product_id 
        WHERE s.quantity > 0 
        ORDER BY p.name";
if($result = mysqli_query($conn, $sql)){
    while($row = mysqli_fetch_assoc($result)){
        $products[] = $row;
    }
    mysqli_free_result($result);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales - TV Inventory System</title>
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
        <span class="navbar-brand">Sales</span>
        <span class="navbar-text ml-auto">
            Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?> (<?php echo htmlspecialchars($_SESSION["role"]); ?>)
        </span>
    </nav>

    <!-- Main Content -->
    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Sales</h2>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addSaleModal">
                <i class="fas fa-plus mr-2"></i>Add New Sale
            </button>
        </div>

        <!-- Sales Table -->
        <div class="table-responsive">
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Salesperson</th>
                        <th>Total Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($sales)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No sales records found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($sales as $sale): ?>
                            <tr>
                                <td><?php echo $sale['id']; ?></td>
                                <td><?php echo date("Y-m-d H:i:s", strtotime($sale['sale_date'])); ?></td>
                                <td><?php echo $sale['customer_name']; ?></td>
                                <td><?php echo $sale['user_name']; ?></td>
                                <td>Rp <?php echo number_format($sale['total_amount'], 0, ',', '.'); ?></td>
                                <td>
                                    <a href="view_sale.php?id=<?php echo $sale['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="sales.php?delete=<?php echo $sale['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this sale?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Sale Modal -->
    <div class="modal fade" id="addSaleModal" tabindex="-1" role="dialog" aria-labelledby="addSaleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSaleModalLabel">Add New Sale</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="form-group">
                            <label>Customer</label>
                            <select name="customer_id" class="form-control" required>
                                <option value="">Select Customer</option>
                                <?php foreach($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>"><?php echo $customer['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Product</label>
                            <select name="product_id" class="form-control" required>
                                <option value="">Select Product</option>
                                <?php foreach($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?> (Stock: <?php echo $product['quantity']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>
                        <div class="form-group">
                            <input type="submit" class="btn btn-primary" value="Submit" name="add_sale">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
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

// Add new purchase
if(isset($_POST["add_purchase"])){
    $supplier_id = $_POST["supplier_id"];
    $product_id = $_POST["product_id"];
    $quantity = $_POST["quantity"];
    $unit_price = $_POST["unit_price"];
    
    // Calculate total amount
    $total_amount = $unit_price * $quantity;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Insert purchase record
        $sql = "INSERT INTO purchases (supplier_id, user_id, total_amount) VALUES (?, ?, ?)";
        $purchase_id = 0;
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "iid", $param_supplier_id, $param_user_id, $param_total_amount);
            $param_supplier_id = $supplier_id;
            $param_user_id = $_SESSION["id"];
            $param_total_amount = $total_amount;
            mysqli_stmt_execute($stmt);
            $purchase_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
        }
        
        // Insert purchase detail record
        $sql = "INSERT INTO purchase_details (purchase_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "iiid", $param_purchase_id, $param_product_id, $param_quantity, $param_unit_price);
            $param_purchase_id = $purchase_id;
            $param_product_id = $product_id;
            $param_quantity = $quantity;
            $param_unit_price = $unit_price;
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // Update stock
        $sql = "UPDATE stock SET quantity = quantity + ? WHERE product_id = ?";
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "ii", $param_quantity, $param_product_id);
            $param_quantity = $quantity;
            $param_product_id = $product_id;
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Redirect to purchases page
        header("location: purchases.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        echo "Error: " . $e->getMessage();
    }
}

// Delete purchase
if(isset($_GET["delete"])){
    $id = $_GET["delete"];
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get purchase details to update stock
        $sql = "SELECT product_id, quantity FROM purchase_details WHERE purchase_id = ?";
        $purchase_details = [];
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $id);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_assoc($result)){
                    $purchase_details[] = $row;
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        // Update stock for each product
        foreach($purchase_details as $detail){
            $sql = "UPDATE stock SET quantity = quantity - ? WHERE product_id = ?";
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "ii", $param_quantity, $param_product_id);
                $param_quantity = $detail['quantity'];
                $param_product_id = $detail['product_id'];
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        
        // Delete purchase details
        $sql = "DELETE FROM purchase_details WHERE purchase_id = ?";
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $param_id);
            $param_id = $id;
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // Delete purchase
        $sql = "DELETE FROM purchases WHERE id = ?";
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $param_id);
            $param_id = $id;
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Redirect to purchases page
        header("location: purchases.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        echo "Error: " . $e->getMessage();
    }
}

// Get purchases data with supplier and user information
 $purchases = [];
 $sql = "SELECT p.id, p.purchase_date, p.total_amount, s.name AS supplier_name, u.name AS user_name 
        FROM purchases p 
        JOIN suppliers s ON p.supplier_id = s.id 
        JOIN users u ON p.user_id = u.id 
        ORDER BY p.purchase_date DESC";
if($result = mysqli_query($conn, $sql)){
    while($row = mysqli_fetch_assoc($result)){
        $purchases[] = $row;
    }
    mysqli_free_result($result);
}

// Get suppliers
 $suppliers = [];
 $sql = "SELECT id, name FROM suppliers ORDER BY name";
if($result = mysqli_query($conn, $sql)){
    while($row = mysqli_fetch_assoc($result)){
        $suppliers[] = $row;
    }
    mysqli_free_result($result);
}

// Get products
 $products = [];
 $sql = "SELECT id, name FROM products ORDER BY name";
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
    <title>Purchases - TV Inventory System</title>
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
                <a class="nav-link active" href="purchases.php">
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
        <span class="navbar-brand">Purchases</span>
        <span class="navbar-text ml-auto">
            Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?> (<?php echo htmlspecialchars($_SESSION["role"]); ?>)
        </span>
    </nav>

    <!-- Main Content -->
    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Purchases</h2>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addPurchaseModal">
                <i class="fas fa-plus mr-2"></i>Add New Purchase
            </button>
        </div>

        <!-- Purchases Table -->
        <div class="table-responsive">
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Buyer</th>
                        <th>Total Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($purchases)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No purchase records found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($purchases as $purchase): ?>
                            <tr>
                                <td><?php echo $purchase['id']; ?></td>
                                <td><?php echo date("Y-m-d H:i:s", strtotime($purchase['purchase_date'])); ?></td>
                                <td><?php echo $purchase['supplier_name']; ?></td>
                                <td><?php echo $purchase['user_name']; ?></td>
                                <td>Rp <?php echo number_format($purchase['total_amount'], 0, ',', '.'); ?></td>
                                <td>
                                    <a href="view_purchase.php?id=<?php echo $purchase['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="purchases.php?delete=<?php echo $purchase['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this purchase?');">
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

    <!-- Add Purchase Modal -->
    <div class="modal fade" id="addPurchaseModal" tabindex="-1" role="dialog" aria-labelledby="addPurchaseModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPurchaseModalLabel">Add New Purchase</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="form-group">
                            <label>Supplier</label>
                            <select name="supplier_id" class="form-control" required>
                                <option value="">Select Supplier</option>
                                <?php foreach($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>"><?php echo $supplier['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Product</label>
                            <select name="product_id" class="form-control" required>
                                <option value="">Select Product</option>
                                <?php foreach($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>Unit Price ($)</label>
                            <input type="number" name="unit_price" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <input type="submit" class="btn btn-primary" value="Submit" name="add_purchase">
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
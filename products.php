<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Include config file
require_once "config.php";

// Define variables and initialize with empty values
 $name = $description = $brand = $model = $price = "";
 $name_err = $description_err = $brand_err = $model_err = $price_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_product"])){
    
    // Validate name
    if(empty(trim($_POST["name"]))){
        $name_err = "Please enter a product name.";
    } else{
        $name = trim($_POST["name"]);
    }
    
    // Validate description
    if(empty(trim($_POST["description"]))){
        $description_err = "Please enter a description.";
    } else{
        $description = trim($_POST["description"]);
    }
    
    // Validate brand
    if(empty(trim($_POST["brand"]))){
        $brand_err = "Please enter a brand.";
    } else{
        $brand = trim($_POST["brand"]);
    }
    
    // Validate model
    if(empty(trim($_POST["model"]))){
        $model_err = "Please enter a model.";
    } else{
        $model = trim($_POST["model"]);
    }
    
    // Validate price
    if(empty(trim($_POST["price"]))){
        $price_err = "Please enter a price.";
    } else{
        $price = trim($_POST["price"]);
    }
    
    // Check input errors before inserting in database
    if(empty($name_err) && empty($description_err) && empty($brand_err) && empty($model_err) && empty($price_err)){
        // Prepare an insert statement
        $sql = "INSERT INTO products (name, description, brand, model, price) VALUES (?, ?, ?, ?, ?)";
         
        if($stmt = mysqli_prepare($conn, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssssd", $param_name, $param_description, $param_brand, $param_model, $param_price);
            
            // Set parameters
            $param_name = $name;
            $param_description = $description;
            $param_brand = $brand;
            $param_model = $model;
            $param_price = $price;
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Get the last inserted product ID
                $product_id = mysqli_insert_id($conn);
                
                // Add stock entry for the new product
                $sql_stock = "INSERT INTO stock (product_id, quantity, min_quantity) VALUES (?, 0, 5)";
                if($stmt_stock = mysqli_prepare($conn, $sql_stock)){
                    mysqli_stmt_bind_param($stmt_stock, "i", $param_product_id);
                    $param_product_id = $product_id;
                    mysqli_stmt_execute($stmt_stock);
                    mysqli_stmt_close($stmt_stock);
                }
                
                // Records created successfully. Redirect to landing page
                header("location: products.php");
                exit();
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($conn);
}

// Delete product
if(isset($_GET["delete"])){
    $id = $_GET["delete"];
    
    // Prepare a delete statement
    $sql = "DELETE FROM products WHERE id = ?";
    
    if($stmt = mysqli_prepare($conn, $sql)){
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "i", $param_id);
        
        // Set parameters
        $param_id = $id;
        
        // Attempt to execute the prepared statement
        if(mysqli_stmt_execute($stmt)){
            // Record deleted successfully. Redirect to landing page
            header("location: products.php");
            exit();
        } else{
            echo "Oops! Something went wrong. Please try again later.";
        }

        // Close statement
        mysqli_stmt_close($stmt);
    }
    
    // Close connection
    mysqli_close($conn);
}

// Get products data with stock information
 $products = [];
 $sql = "SELECT p.id, p.name, p.description, p.brand, p.model, p.price, s.quantity, s.min_quantity 
        FROM products p 
        LEFT JOIN stock s ON p.id = s.product_id 
        ORDER BY p.id ASC";

if($result = mysqli_query($conn, $sql)){
    while($row = mysqli_fetch_assoc($result)){
        // Pastikan kolom price ada dan valid
        if(!isset($row['price']) || !is_numeric($row['price'])) {
            $row['price'] = 0;
        }
        $products[] = $row;
    }
    mysqli_free_result($result);
} else {
    // Tampilkan error untuk debugging
    echo "<div class='alert alert-danger'>";
    echo "<strong>Database Error:</strong> " . mysqli_error($conn);
    echo "<br><strong>Query:</strong> " . $sql;
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - TV Inventory System</title>
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
                <a class="nav-link active" href="products.php">
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
        <span class="navbar-brand">Products</span>
        <span class="navbar-text ml-auto">
            Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?> (<?php echo htmlspecialchars($_SESSION["role"]); ?>)
        </span>
    </nav>

    <!-- Main Content -->
    <div class="content">
        <?php if($_SESSION["role"] == "admin" || $_SESSION["role"] == "karyawan"): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Products</h2>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addProductModal">
                <i class="fas fa-plus mr-2"></i>Add New Product
            </button>
        </div>

        <!-- Products Table -->
        <div class="table-responsive">
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($products)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No products found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($products as $product): ?>
                            <tr>
                                <td><?php echo $product['id']; ?></td>
                                <td><?php echo $product['name']; ?></td>
                                <td><?php echo $product['brand']; ?></td>
                                <td><?php echo $product['model']; ?></td>
                                <td>Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php 
                                    if($product['quantity'] == 0){
                                        echo '<span class="stock-out">' . $product['quantity'] . ' (Out of Stock)</span>';
                                    } elseif($product['quantity'] <= $product['min_quantity']){
                                        echo '<span class="stock-low">' . $product['quantity'] . ' (Low Stock)</span>';
                                    } else{
                                        echo '<span class="stock-good">' . $product['quantity'] . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="products.php?delete=<?php echo $product['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <!-- Pembeli View -->
        <h2 class="mb-4">Available TVs</h2>
        <div class="row">
            <?php if(empty($products)): ?>
                <div class="col-12">
                    <div class="alert alert-info">No products available at the moment.</div>
                </div>
            <?php else: ?>
                <?php foreach($products as $product): ?>
                <div class="col-md-4 mb-4">
                    <div class="card product-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $product['name']; ?></h5>
                            <p class="card-text">
                                <strong>Brand:</strong> <?php echo $product['brand']; ?><br>
                                <strong>Model:</strong> <?php echo $product['model']; ?><br>
                                <strong>Price:</strong> $<?php echo number_format($product['price'], 2); ?><br>
                                <strong>Stock:</strong> 
                                <?php 
                                if($product['quantity'] == 0){
                                    echo '<span class="stock-out">Out of Stock</span>';
                                } elseif($product['quantity'] <= $product['min_quantity']){
                                    echo '<span class="stock-low">Only ' . $product['quantity'] . ' left</span>';
                                } else{
                                    echo '<span class="stock-good">' . $product['quantity'] . ' available</span>';
                                }
                                ?>
                            </p>
                            <?php if($product['quantity'] > 0): ?>
                            <a href="buy_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-block">Buy Now</a>
                            <?php else: ?>
                            <button class="btn btn-secondary btn-block" disabled>Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Product Modal -->
    <?php if($_SESSION["role"] == "admin" || $_SESSION["role"] == "karyawan"): ?>
    <div class="modal fade" id="addProductModal" tabindex="-1" role="dialog" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $name; ?>">
                            <span class="invalid-feedback"><?php echo $name_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control <?php echo (!empty($description_err)) ? 'is-invalid' : ''; ?>"><?php echo $description; ?></textarea>
                            <span class="invalid-feedback"><?php echo $description_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Brand</label>
                            <input type="text" name="brand" class="form-control <?php echo (!empty($brand_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $brand; ?>">
                            <span class="invalid-feedback"><?php echo $brand_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <input type="text" name="model" class="form-control <?php echo (!empty($model_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $model; ?>">
                            <span class="invalid-feedback"><?php echo $model_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Price ($)</label>
                            <input type="number" name="price" step="0.01" class="form-control <?php echo (!empty($price_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $price; ?>">
                            <span class="invalid-feedback"><?php echo $price_err; ?></span>
                        </div>
                        <div class="form-group">
                            <input type="submit" class="btn btn-primary" value="Submit" name="add_product">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
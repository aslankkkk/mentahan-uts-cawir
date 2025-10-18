<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Check if user has permission to access this page
if($_SESSION["role"] != "admin"){
    header("location: dashboard.php");
    exit;
}

// Include config file
require_once "config.php";

// Check if user id is provided
if(!isset($_GET["id"]) || empty($_GET["id"])){
    header("location: users.php");
    exit;
}

 $user_id = $_GET["id"];

// Get user data
 $sql = "SELECT * FROM users WHERE id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if(mysqli_stmt_execute($stmt)){
        $result = mysqli_stmt_get_result($stmt);
        if(mysqli_num_rows($result) == 1){
            $user = mysqli_fetch_assoc($result);
        } else{
            header("location: users.php");
            exit;
        }
    } else{
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Define variables and initialize with empty values
 $username = $user['username'];
 $password = "";
 $role = $user['role'];
 $name = $user['name'];
 $email = $user['email'];
 $username_err = $password_err = $role_err = $name_err = $email_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Validate username
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter a username.";
    } else{
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = ? AND id != ?";
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "si", $param_username, $param_id);
            $param_username = trim($_POST["username"]);
            $param_id = $user_id;
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $username_err = "This username is already taken.";
                } else{
                    $username = trim($_POST["username"]);
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Validate password (optional)
    if(!empty(trim($_POST["password"]))){
        if(strlen(trim($_POST["password"])) < 6){
            $password_err = "Password must have at least 6 characters.";
        } else{
            $password = trim($_POST["password"]);
        }
    }
    
    // Validate role
    if(empty(trim($_POST["role"]))){
        $role_err = "Please select a role.";     
    } else{
        $role = trim($_POST["role"]);
    }
    
    // Validate name
    if(empty(trim($_POST["name"]))){
        $name_err = "Please enter a name.";     
    } else{
        $name = trim($_POST["name"]);
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter an email.";     
    } else{
        $email = trim($_POST["email"]);
    }
    
    // Check input errors before updating in database
    if(empty($username_err) && empty($password_err) && empty($role_err) && empty($name_err) && empty($email_err)){
        // Prepare an update statement
        if(empty($password)){
            $sql = "UPDATE users SET username = ?, role = ?, name = ?, email = ? WHERE id = ?";
            if($stmt = mysqli_prepare($conn, $sql)){
                // Bind variables to the prepared statement as parameters
                mysqli_stmt_bind_param($stmt, "ssssi", $param_username, $param_role, $param_name, $param_email, $param_id);
                
                // Set parameters
                $param_username = $username;
                $param_role = $role;
                $param_name = $name;
                $param_email = $email;
                $param_id = $user_id;
                
                // Attempt to execute the prepared statement
                if(mysqli_stmt_execute($stmt)){
                    // Records updated successfully. Redirect to landing page
                    header("location: users.php");
                    exit();
                } else{
                    echo "Oops! Something went wrong. Please try again later.";
                }

                // Close statement
                mysqli_stmt_close($stmt);
            }
        } else{
            $sql = "UPDATE users SET username = ?, password = ?, role = ?, name = ?, email = ? WHERE id = ?";
            if($stmt = mysqli_prepare($conn, $sql)){
                // Bind variables to the prepared statement as parameters
                mysqli_stmt_bind_param($stmt, "sssssi", $param_username, $param_password, $param_role, $param_name, $param_email, $param_id);
                
                // Set parameters
                $param_username = $username;
                $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
                $param_role = $role;
                $param_name = $name;
                $param_email = $email;
                $param_id = $user_id;
                
                // Attempt to execute the prepared statement
                if(mysqli_stmt_execute($stmt)){
                    // Records updated successfully. Redirect to landing page
                    header("location: users.php");
                    exit();
                } else{
                    echo "Oops! Something went wrong. Please try again later.";
                }

                // Close statement
                mysqli_stmt_close($stmt);
            }
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
    <title>Edit User - TV Inventory System</title>
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
                <a class="nav-link" href="my_orders.php">
                    <i class="fas fa-list-alt mr-2"></i> My Orders
                </a>
            </li>
            <?php endif; ?>
            <?php if($_SESSION["role"] == "admin"): ?>
            <li class="nav-item">
                <a class="nav-link active" href="users.php">
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
        <span class="navbar-brand">Edit User</span>
        <span class="navbar-text ml-auto">
            Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?> (<?php echo htmlspecialchars($_SESSION["role"]); ?>)
        </span>
    </nav>

    <!-- Main Content -->
    <div class="content">
        <div class="card">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Edit User Information</h6>
            </div>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $user_id); ?>" method="post">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                        <span class="invalid-feedback"><?php echo $username_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Password <small>(Leave blank to keep current password)</small></label>
                        <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>">
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" class="form-control <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Role</option>
                            <option value="admin" <?php echo ($role == "admin") ? "selected" : ""; ?>>Admin</option>
                            <option value="karyawan" <?php echo ($role == "karyawan") ? "selected" : ""; ?>>Karyawan</option>
                            <option value="pembeli" <?php echo ($role == "pembeli") ? "selected" : ""; ?>>Pembeli</option>
                        </select>
                        <span class="invalid-feedback"><?php echo $role_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $name; ?>">
                        <span class="invalid-feedback"><?php echo $name_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    </div>
                    <div class="form-group">
                        <input type="submit" class="btn btn-primary" value="Update">
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
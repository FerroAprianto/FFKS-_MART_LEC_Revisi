<?php
session_start();
require_once __DIR__ . '/../PHP/config.php';


if (isset($_SESSION['valid'])) {
    header("Location: index.php");
    exit;
}

if (isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit;
}

$error_msg = "";

if (isset($_POST['submit'])) {
    
    
    $input_login = mysqli_real_escape_string($con, trim($_POST['email'])); 
    $password    = mysqli_real_escape_string($con, trim($_POST['password']));


    $pass_md5 = md5($password);
   
    $stmt_admin = mysqli_prepare($con, "SELECT * FROM admin WHERE username = ? AND password = ?");
    mysqli_stmt_bind_param($stmt_admin, "ss", $input_login, $pass_md5);
    mysqli_stmt_execute($stmt_admin);
    $result_admin = mysqli_stmt_get_result($stmt_admin);

    if (mysqli_num_rows($result_admin) > 0) {
        $row = mysqli_fetch_assoc($result_admin);
        
        $_SESSION['admin_id'] = $row['id_admin'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role']     = 'admin';

        header("Location: admin.php");
        exit;
    }
    mysqli_stmt_close($stmt_admin);


   
    $stmt_cust = mysqli_prepare($con, "SELECT * FROM customer WHERE Email = ? OR Username = ?");
    mysqli_stmt_bind_param($stmt_cust, "ss", $input_login, $input_login);
    mysqli_stmt_execute($stmt_cust);
    $result_cust = mysqli_stmt_get_result($stmt_cust);
    $row = mysqli_fetch_assoc($result_cust);

    if ($row) {
      
        if (password_verify($password, $row['Password'])) {
            
           
            $_SESSION['valid']    = $row['id_customer'];
            $_SESSION['username'] = $row['Username'];
            $_SESSION['email']    = $row['Email'];
            $_SESSION['age']      = $row['Age'];

            header("Location: index.php");
            exit;

        } else {
            $error_msg = "Password salah!";
        }
    } else {
        $error_msg = "Email atau Username tidak ditemukan!";
    }
    
    mysqli_stmt_close($stmt_cust);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | FFKS MART</title>
    <link rel="stylesheet" href="../CSS/login.css">
    <link rel="shortcut icon" href="../ASSET/logo-Url.png" />
</head>
<body>
    <div class="container">
        <div class="box form-box">
            
            <?php if(!empty($error_msg)): ?>
            <div class="mesagge" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 10px;">
                <p><?php echo htmlspecialchars($error_msg); ?></p>
            </div>
            <?php endif; ?>

            <header>Login</header>
            
            <form action="" method="post">
                <div class="field input">
                    <label for="email">Email</label>
                    <input type="text" name="email" id="email" autocomplete="off" required>
                </div>

                <div class="field input">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" autocomplete="off" required>
                </div>

                <div class="field">
                    <input type="submit" class="btn" name="submit" value="Login" required>
                </div>
                
                <div class="links">
                    Belum punya akun? <a href="register.php">Register</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
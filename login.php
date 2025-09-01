<!-- login.php -->
<?php
session_start();
require 'includes/db.php';

$email = $password = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password'];

    // Validation
    if (empty($email)) { $errors[] = "Email is required."; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Invalid email format."; }
    if (empty($password)) { $errors[] = "Password is required."; }

    if (empty($errors)) {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, name, password, phone, gender, photo FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $name, $hashed_password, $phone, $gender, $photo);
                $stmt->fetch();
                if (password_verify($password, $hashed_password)) {
                    $_SESSION['user_id'] = $id;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_phone'] = $phone;
                    $_SESSION['user_gender'] = $gender;
                    $_SESSION['user_photo'] = $photo;
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $errors[] = "Incorrect password.";
                }
            } else {
                $errors[] = "No account found with that email.";
            }
            $stmt->close();
        } else {
            $errors[] = "Database error.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - NoteNest</title>
    <link rel="shortcut icon" href="img/fav.ico" type="image/x-icon">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <div class="container" id="container">
        <div class="form-container sign-in-container">
            <form action="login.php" method="POST">
                <h1>Sign In to NoteNest</h1>
                <div class="social-container">
                    <a href="#" class="social"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social"><i class="fab fa-google-plus-g"></i></a>
                    <a href="#" class="social"><i class="fab fa-linkedin-in"></i></a>
                </div>
                <span>or use your NoteNest credentials</span>
                <input type="email" name="email" required placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" />
                <input type="password" name="password" required placeholder="Password" value="<?php echo htmlspecialchars($password); ?>"/>
                <a href="#">Forgot your password?</a>
                <?php
                    if (!empty($errors)) {
                        echo '<div class="error">';
                        foreach ($errors as $error) {
                            echo $error . "<br>";
                        }
                        echo '</div>';
                    }
                ?>
                <button type="submit" class="btn btn-color">Log In</button>
            </form>
        </div>
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-right">
                    <h1>Manage Your Files!</h1>
                    <p>Welcome to NoteNest. Don't have an account?</p>
                    <button class="ghost"><a href="register.php">Sign Up</a></button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
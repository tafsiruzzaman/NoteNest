<?php
require 'includes/db.php';

$name = $email = $password = $confirm_password = $phone = $gender = "";
$photo = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = htmlspecialchars(trim($_POST['phone']));
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $photo = isset($_FILES['photo']) ? $_FILES['photo'] : null;

    // Validation
    if (empty($name)) { $errors[] = "Name is required."; }
    if (empty($email)) { $errors[] = "Email is required."; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Invalid email format."; }
    if (empty($password)) { $errors[] = "Password is required."; }
    if (strlen($password) < 6) { $errors[] = "Password must be at least 6 characters."; }
    if ($password !== $confirm_password) { $errors[] = "Passwords do not match."; }
    if (empty($phone)) { $errors[] = "Phone is required."; }
    if (empty($gender)) { $errors[] = "Gender is required."; }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Email is already registered.";
        }
        $stmt->close();
    } else {
        $errors[] = "Database error.";
    }

    // If no errors, insert the user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $photo_path = 'img/user.png';
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, gender, photo) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssssss", $name, $email, $hashed_password, $phone, $gender, $photo_path);
            if ($stmt->execute()) {
                header("Location: login.php");
                exit();
            } else {
                $errors[] = "Registration failed. Please try again.";
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
    <title>Sign Up - NoteNest</title>
    <link rel="shortcut icon" href="img/fav.ico" type="image/x-icon">
    <link rel="stylesheet" href="css/register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="container" id="container">
        <div class="form-container sign-in-container">
            <form action="register.php" method="POST">
                <h1>Create NoteNest Account</h1>
                <div class="social-container">
					<a href="#" class="social"><i class="fab fa-facebook-f"></i></a>
					<a href="#" class="social"><i class="fab fa-google-plus-g"></i></a>
					<a href="#" class="social"><i class="fab fa-linkedin-in"></i></a>
				</div>
                <input type="text" name="name" placeholder="Name" value="<?php echo htmlspecialchars($name); ?>" required>
                <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <input type="text" name="phone" placeholder="Phone" value="<?php echo htmlspecialchars($phone); ?>" required>
                <select name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?php if($gender=='Male') echo 'selected'; ?>>Male</option>
                    <option value="Female" <?php if($gender=='Female') echo 'selected'; ?>>Female</option>
                    <option value="Other" <?php if($gender=='Other') echo 'selected'; ?>>Other</option>
                </select>
                <?php
                    if (!empty($errors)) {
                        echo '<div class="error">';
                        foreach ($errors as $error) {
                            echo $error . "<br>";
                        }
                        echo '</div>';
                    }
                ?>
                <button type="submit" class="btn btn-color">Sign Up</button>
            </form>
        </div>
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-right">
                    <h1>Already have an account?</h1>
                    <p>Sign in to start securely managing your notes and files!</p>
                    <button class="ghost"><a href="login.php">Sign In</a></button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
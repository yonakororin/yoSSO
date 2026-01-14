<?php
session_start();

if (!isset($_SESSION['yosso_user'])) {
    header("Location: index.php");
    exit;
}

$users_file = __DIR__ . '/data/users.json';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $users = json_decode(file_get_contents($users_file), true);
    $username = $_SESSION['yosso_user'];

    if (!isset($users[$username])) {
        $error = 'User not found.';
    } elseif (!password_verify($current_password, $users[$username]['password'])) {
        $error = 'Current password is incorrect.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 4) {
        $error = 'Password must be at least 4 characters.';
    } else {
        $users[$username]['password'] = password_hash($new_password, PASSWORD_DEFAULT);
        file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT));
        $message = 'Password updated successfully.';
    }
}

$redirect_back = $_GET['redirect_uri'] ?? 'index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - yoSSO</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="logo">
            Change Password
        </div>
        
        <?php if ($message): ?>
            <div class="error-message" style="color: var(--success-color); background: rgba(34, 197, 94, 0.1); border-color: rgba(34, 197, 94, 0.2);">
                <?= htmlspecialchars($message) ?>
            </div>
            <a href="<?= htmlspecialchars($redirect_back) ?>"><button>Return to App</button></a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="current_password" name="current_password" required>
                        <span class="toggle-password" onclick="togglePassword('current_password', this)">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_password" name="new_password" required>
                        <span class="toggle-password" onclick="togglePassword('new_password', this)">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <span class="toggle-password" onclick="togglePassword('confirm_password', this)">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                </div>
                <button type="submit">Update Password</button>
            </form>
            <div class="footer-text">
                <a href="<?= htmlspecialchars($redirect_back) ?>" style="color: var(--text-secondary); text-decoration: none;">Cancel</a>
            </div>
        <?php endif; ?>
    </div>
    <script>
        const eyeIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
        const eyeOffIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;

        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.innerHTML = eyeOffIcon; // Change to 'Hide' icon
                icon.style.opacity = "1";
            } else {
                input.type = "password";
                icon.innerHTML = eyeIcon; // Change to 'Show' icon
                icon.style.opacity = "1";
            }
        }
    </script>
</body>
</html>

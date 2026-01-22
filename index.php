<?php
require_once __DIR__ . '/../shared/session_config.php';
session_start();

$users_dir = __DIR__ . '/../mnguser/data/users/';
$codes_file = __DIR__ . '/data/codes.json';

// Initialize data files if not exist
if (!file_exists(__DIR__ . '/data')) mkdir(__DIR__ . '/data');
// if (!file_exists($users_file)) ... (No longer creating users.json)
if (!file_exists($codes_file)) file_put_contents($codes_file, '{}');

function get_users() {
    global $users_dir;
    $users = [];
    if (is_dir($users_dir)) {
        $files = glob($users_dir . '*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['username'])) {
                $users[$data['username']] = $data;
            }
        }
    }
    return $users;
}

function save_code($code, $username) {
    global $codes_file;
    $codes = json_decode(file_get_contents($codes_file), true);
    // Cleanup old codes
    foreach ($codes as $c => $data) {
        if ($data['expires_at'] < time()) unset($codes[$c]);
    }
    $codes[$code] = [
        'username' => $username,
        'expires_at' => time() + 300 // 5 minutes
    ];
    file_put_contents($codes_file, json_encode($codes));
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$error = '';
$redirect_uri = $_GET['redirect_uri'] ?? '';

// Load System Config
$config_file = __DIR__ . '/data/config.json';
$config = [];
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);
}
$system_name = $config['system_name'] ?? 'yoSSO';
$target_app = $_GET['app_name'] ?? '';
$target_env = $config['target_env'] ?? 'dev';
$base_color = $config['base_color'] ?? null;

// Login Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    // ... no change needed here ...
    $password = $_POST['password'] ?? '';
    
    $users = get_users();
    
    if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
        $_SESSION['yosso_user'] = $username;
    } else {
        $error = 'Invalid credentials';
    }
}

// If Logged In
if (isset($_SESSION['yosso_user'])) {
    if ($redirect_uri) {
        $code = bin2hex(random_bytes(16));
        save_code($code, $_SESSION['yosso_user']);
        $sep = (strpos($redirect_uri, '?') === false) ? '?' : '&';
        header("Location: " . $redirect_uri . $sep . "code=" . $code);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($system_name) ?> - Sign In</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <?= htmlspecialchars($system_name) ?>
        </div>
        <?php if ($target_app && $target_app !== $system_name): ?>
            <div style="text-align: center; margin-top: -0.5rem; margin-bottom: 2rem; color: var(--text-secondary); font-size: 0.9rem;">
                to <?= htmlspecialchars($target_app) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!isset($_SESSION['yosso_user'])): ?>
            <?php if ($error): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="?<?= http_build_query($_GET) ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus placeholder="Enter your username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required placeholder="Enter your password">
                        <span class="toggle-password" onclick="togglePassword('password', this)">
                            <!-- Eye Icon (Default) -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                </div>
                <button type="submit">Sign In</button>
            </form>
        <?php else: ?>
            <div style="text-align: center;">
                <h2 style="margin-bottom: 1rem; color: var(--text-primary);">Welcome, <?= htmlspecialchars($_SESSION['yosso_user']) ?></h2>
                <p style="color: var(--text-secondary); margin-bottom: 2rem;">You are currently logged in.</p>
                <a href="?logout=1"><button style="background-color: var(--card-bg); border: 1px solid rgba(255,255,255,0.1);">Sign Out</button></a>
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
                icon.style.opacity = "1"; // Keep full opacity for SVG
            }
        }
    </script>
    <script>
        window.currentUser = "<?= isset($_SESSION['yosso_user']) ? htmlspecialchars($_SESSION['yosso_user']) : '' ?>";
        window.mngConfig = <?= json_encode([
            'target_env' => $target_env,
            'base_color' => $base_color
        ]) ?>;
    </script>
    <script src="../shared/theme.js"></script>
</body>
</html>

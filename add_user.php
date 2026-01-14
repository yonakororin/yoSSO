<?php
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

$users_file = __DIR__ . '/data/users.json';
if (!file_exists($users_file)) {
    die("Error: data/users.json not found.\n");
}

echo "yoSSO User Registration\n";
echo "-----------------------\n";

$username = readline("Username: ");
if (!$username) die("Username is required.\n");

$users = json_decode(file_get_contents($users_file), true);
if (isset($users[$username])) {
    echo "Warning: User '$username' already exists. Overwrite? (y/n): ";
    $resp = trim(fgets(STDIN));
    if (strtolower($resp) !== 'y') {
        die("Aborted.\n");
    }
}

echo "Password: ";
system('stty -echo');
$password = trim(fgets(STDIN));
system('stty echo');
echo "\n";

if (!$password) die("Password is required.\n");

$name = readline("Display Name (e.g. John Doe): ");
if (!$name) $name = $username;

// Create hash
$hash = password_hash($password, PASSWORD_DEFAULT);

// Save
$users[$username] = [
    'password' => $hash,
    'name' => $name
];

file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT));
echo "User '$username' registered successfully.\n";

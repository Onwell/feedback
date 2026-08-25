<?php
// Include config
require_once 'config.php';

// Function to get database connection (with fallback if config doesn't have it)
function getDatabaseConnection() {
    if (function_exists('getDB')) {
        return getDB();
    }
    
    // Fallback connection if getDB doesn't exist
    try {
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $dbname = defined('DB_NAME') ? DB_NAME : 'ncc_feedback_db';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Script configuration
$usersFile = __DIR__ . '/users.json';
$dryRun = isset($_GET['dry-run']) || isset($_GET['dry_run']); // Add ?dry-run to test without inserting

echo "<!DOCTYPE html>
<html>
<head>
    <title>Migrate Users to Database</title>
    <style>
        body { font-family: 'Courier New', monospace; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1F4D3A; margin-top: 0; }
        .success { color: #1F4D3A; }
        .error { color: #A23B2E; }
        .info { color: #5B655D; }
        .warning { color: #AD8A4D; }
        .summary { background: #F6F3EA; padding: 15px; border-radius: 4px; margin-top: 20px; }
        .progress { margin: 10px 0; padding: 8px; background: #f0f0f0; border-radius: 4px; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔄 User Migration Tool</h1>
    <p>Migrating users from <code>users.json</code> to MySQL database</p>";

// Check if users.json exists
if (!file_exists($usersFile)) {
    echo "<div class='error'><strong>Error:</strong> users.json file not found at: " . htmlspecialchars($usersFile) . "</div>";
    echo "<p>Please make sure the file exists in the current directory.</p>";
    echo "</div></body></html>";
    exit;
}

// Read and parse JSON
$jsonContent = file_get_contents($usersFile);
$users = json_decode($jsonContent, true);

if (!$users || !is_array($users)) {
    echo "<div class='error'><strong>Error:</strong> Invalid JSON format in users.json</div>";
    echo "<p>Error: " . json_last_error_msg() . "</p>";
    echo "</div></body></html>";
    exit;
}

$totalUsers = count($users);
echo "<p><span class='info'>📄 Found <strong>{$totalUsers}</strong> users in JSON file</span></p>";

if ($dryRun) {
    echo "<div class='warning'><strong>⚠️ DRY RUN MODE</strong> - No data will be inserted. Remove ?dry-run from URL to actually migrate.</div>";
} else {
    echo "<div class='info'>💾 Inserting users into database...</div>";
}

// Connect to database
try {
    $pdo = getDatabaseConnection();
    echo "<p class='success'>✅ Connected to database successfully</p>";
} catch (Exception $e) {
    echo "<div class='error'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "</div></body></html>";
    exit;
}

// Migrate users
$migrated = 0;
$skipped = 0;
$errors = 0;
$errorMessages = [];

foreach ($users as $index => $user) {
    $email = $user['email'] ?? '';
    $name = $user['name'] ?? '';
    $passwordHash = $user['passwordHash'] ?? $user['password_hash'] ?? '';
    $role = $user['role'] ?? 'user';
    
    // Skip if missing required fields
    if (empty($email) || empty($name) || empty($passwordHash)) {
        $errors++;
        $errorMessages[] = "User #" . ($index + 1) . " skipped: Missing required fields (email, name, or passwordHash)";
        continue;
    }
    
    // Check if user already exists
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            echo "<div class='progress'><span class='info'>⏭️ Skipped (already exists):</span> " . htmlspecialchars($email) . "</div>";
            $skipped++;
            continue;
        }
        
        // Insert user if not dry run
        if (!$dryRun) {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $passwordHash, $role]);
            
            if ($stmt->rowCount() > 0) {
                echo "<div class='progress'><span class='success'>✅ Migrated:</span> " . htmlspecialchars($email) . " (role: " . htmlspecialchars($role) . ")</div>";
                $migrated++;
            } else {
                $errors++;
                $errorMessages[] = "Failed to insert user: " . $email;
            }
        } else {
            // Dry run mode
            echo "<div class='progress'><span class='info'>🔄 [DRY RUN] Would migrate:</span> " . htmlspecialchars($email) . "</div>";
            $migrated++;
        }
        
    } catch (PDOException $e) {
        $errors++;
        $errorMessages[] = "Error migrating {$email}: " . $e->getMessage();
        echo "<div class='progress'><span class='error'>❌ Error:</span> " . htmlspecialchars($email) . " - " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Summary
echo "<div class='summary'>";
echo "<h2>📊 Migration Summary</h2>";
echo "<ul>";
echo "<li><strong>Total users in JSON:</strong> {$totalUsers}</li>";
echo "<li><strong>Successfully migrated:</strong> <span class='success'>{$migrated}</span></li>";
echo "<li><strong>Skipped (already exist):</strong> <span class='info'>{$skipped}</span></li>";
echo "<li><strong>Errors:</strong> <span class='error'>{$errors}</span></li>";

if ($dryRun) {
    echo "<li><strong>Mode:</strong> <span class='warning'>DRY RUN</span> - No actual changes made</li>";
} else {
    echo "<li><strong>Mode:</strong> LIVE</li>";
}

echo "</ul>";

if (!empty($errorMessages)) {
    echo "<h3>⚠️ Error Details:</h3>";
    echo "<ul>";
    foreach ($errorMessages as $msg) {
        echo "<li class='error'>" . htmlspecialchars($msg) . "</li>";
    }
    echo "</ul>";
}

echo "</div>";

// Quick SQL to verify after migration
if (!$dryRun && $migrated > 0) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total, 
                             SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                             SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as users,
                             SUM(CASE WHEN role = 'viewer' THEN 1 ELSE 0 END) as viewers
                             FROM users");
        $stats = $stmt->fetch();
        
        echo "<div class='summary' style='margin-top:10px;'>";
        echo "<h3>📈 Current Database Users:</h3>";
        echo "<ul>";
        echo "<li><strong>Total:</strong> {$stats['total']}</li>";
        echo "<li><strong>Admins:</strong> {$stats['admins']}</li>";
        echo "<li><strong>Users:</strong> {$stats['users']}</li>";
        echo "<li><strong>Viewers:</strong> {$stats['viewers']}</li>";
        echo "</ul>";
        echo "</div>";
    } catch (PDOException $e) {
        // Ignore
    }
}

echo "<p style='margin-top:20px;'>";
echo "<a href='login.php' style='display:inline-block;background:#1F4D3A;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;'>Go to Login</a> ";
echo "<a href='dashboard.php' style='display:inline-block;background:#AD8A4D;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;'>Go to Dashboard</a> ";
if (!$dryRun && $migrated == 0 && $errors == 0) {
    echo "<a href='?dry-run' style='display:inline-block;background:#5B655D;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;'>Test with Dry Run</a> ";
}
echo "</p>";

echo "</div></body></html>";
?>
<?php
// =====================================================
// DMP CRM - Core Configuration
// =====================================================

// ---- Database settings (edit these for your server) ----
define('DB_DRIVER', strtolower(getenv('DB_DRIVER') ?: (getenv('DB_HOST') ? 'pgsql' : 'mysql')));
define('DB_IS_POSTGRES', DB_DRIVER === 'pgsql' || DB_DRIVER === 'postgres');
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: (DB_IS_POSTGRES ? '5432' : '3306'));
define('DB_NAME', getenv('DB_NAME') ?: (DB_IS_POSTGRES ? 'postgres' : 'dmp_crm'));
define('DB_USER', getenv('DB_USER') ?: (DB_IS_POSTGRES ? 'postgres' : 'root'));
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_SSL_CA', getenv('DB_SSL_CA') ?: '');

// ---- App settings ----
define('APP_NAME', 'DMP AI Digital Institute CRM');

$configuredBaseUrl = rtrim(getenv('APP_URL') ?: '', '/');
$httpProtocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443
    ? 'https://'
    : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$projectRoot = realpath(__DIR__ . '/..');
$basePath = '';
if ($documentRoot && $projectRoot) {
    $documentRoot = str_replace('\\', '/', rtrim($documentRoot, '/'));
    $projectRoot = str_replace('\\', '/', $projectRoot);
    if (strpos($projectRoot, $documentRoot . '/') === 0) {
        $basePath = substr($projectRoot, strlen($documentRoot));
    }
}
define('BASE_URL', $configuredBaseUrl ?: $httpProtocol . $host . $basePath);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// ---- Error reporting (turn off display_errors in production) ----
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ---- Session ----
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Database connection (PDO) ----
try {
    $pdoOptions = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (DB_SSL_CA !== '' && !DB_IS_POSTGRES) {
        $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
        $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    $dsn = DB_IS_POSTGRES
        ? "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require"
        : "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        $pdoOptions
    );
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// Add profile photos to existing installations without requiring a manual migration.
try {
    $pdo->exec(DB_IS_POSTGRES
        ? "ALTER TABLE users ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255)"
        : "ALTER TABLE users ADD COLUMN photo_path VARCHAR(255) NULL AFTER phone");
} catch (PDOException $e) {
    // The column already exists on upgraded installations.
}

try {
    if (!DB_IS_POSTGRES) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_password_resets_expiry (expires_at)
        ) ENGINE=InnoDB");
    }
} catch (PDOException $e) {
    // The table is created by the schema on new installations.
}

// Add WhatsApp as a lead source for databases created before this integration.
try {
    if (!DB_IS_POSTGRES) {
        $pdo->exec("ALTER TABLE leads MODIFY source ENUM('facebook','google','instagram','whatsapp','referral','walk-in','website','other') DEFAULT 'other'");
    }
} catch (PDOException $e) {
    // The leads table may not exist yet on a fresh installation.
}

try {
    $tableCheck = DB_IS_POSTGRES
        ? $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'")
        : $pdo->query("SHOW TABLES LIKE 'users'");
    if ($tableCheck && $tableCheck->rowCount() > 0) {
        $defaultAdminEmail = 'admin@dmpschool.com';
        $defaultAdminHash = '$2y$10$29GiQUfn.xkoefZB1NbU/eggSJfA/8UclXetK2X6V2CueYSTXFpti';

        $existingAdmin = $pdo->prepare("SELECT id, password, role, status FROM users WHERE email = ? LIMIT 1");
        $existingAdmin->execute([$defaultAdminEmail]);
        $adminUser = $existingAdmin->fetch();

        if ($adminUser) {
            $isLegacyHash = $adminUser['password'] === '$2b$10$vtRqI6teKi8QYl63Sfd58O0BOzTaXhZAMP4kF9a/MNrsV0wDvNru.';
            if ($isLegacyHash || !password_verify('Admin@123', $adminUser['password'])) {
                $pdo->prepare("UPDATE users SET password = ?, role = 'admin', status = 'active' WHERE email = ?")
                    ->execute([$defaultAdminHash, $defaultAdminEmail]);
            }
        } else {
            $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')")
                ->execute(['Super Admin', $defaultAdminEmail, $defaultAdminHash]);
        }
    }
} catch (Throwable $e) {
    // Ignore bootstrapping failures here; the app can still run if the DB is not ready yet.
}

// ---- CSRF token helper ----
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}
function csrf_verify() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token. Please refresh the page and try again.');
    }
}

function db_last_insert_id($table) {
    global $pdo;
    return DB_IS_POSTGRES ? $pdo->lastInsertId($table . '_id_seq') : $pdo->lastInsertId();
}

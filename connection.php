<?php
// ====================================================================
// DATABASE CONNECTION & AUTO-INITIALIZATION (connection.php)
// ====================================================================
$host = 'localhost';
$username = 'root';
$password = ''; // Default password for local servers (XAMPP / Laragon)
$dbname = 'payroll_db';
try {
    // 1. Connect to MySQL server without selecting a database first
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // 2. Query INFORMATION_SCHEMA to see if the database exists
    $stmt = $conn->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :dbname");
    $stmt->execute(['dbname' => $dbname]);
    $db_exists = $stmt->fetch();
    if (!$db_exists) {
        // Create the database
        $conn->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
        $conn->exec("USE `$dbname`;");
        // List of SQL files to run in sequential dependency order
        $sql_files = [
            __DIR__ . '/../sql/database_schema.sql',
            __DIR__ . '/../sql/stored_procedures.sql',
            __DIR__ . '/../sql/triggers.sql',
            __DIR__ . '/../sql/insert_data.sql'
        ];
        foreach ($sql_files as $file_path) {
            if (file_exists($file_path)) {
                $sql_content = file_get_contents($file_path);
                
                // Parser for SQL blocks, handling standard statements and DELIMITER sequences
                $lines = explode("\n", $sql_content);
                $statements = [];
                $current_statement = "";
                $in_delimiter_block = false;
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    
                    // Skip empty lines and SQL comment lines
                    if ($trimmed === "" || strpos($trimmed, "--") === 0) {
                        continue;
                    }
                    // Delimiter block toggle for stored procedures/triggers
                    if (strpos($trimmed, "DELIMITER //") === 0) {
                        $in_delimiter_block = true;
                        continue;
                    }
                    if (strpos($trimmed, "DELIMITER ;") === 0) {
                        $in_delimiter_block = false;
                        continue;
                    }
                    $current_statement .= "\n" . $line;
                    if ($in_delimiter_block) {
                        // In delimiter block, look for '//' suffix
                        if (substr($trimmed, -2) === '//') {
                            $stmt_to_run = substr(trim($current_statement), 0, -2); // strip '//'
                            $statements[] = trim($stmt_to_run);
                            $current_statement = "";
                        }
                    } else {
                        // Standard statement, look for ';' suffix
                        if (substr($trimmed, -1) === ';') {
                            $statements[] = trim($current_statement);
                            $current_statement = "";
                        }
                    }
                }
                // Execute parsed statements
                foreach ($statements as $statement) {
                    if (!empty(trim($statement))) {
                        $conn->exec($statement);
                    }
                }
            }
        }
    } else {
        // Database already exists, select it
        $conn->exec("USE `$dbname`;");
    }
} catch (PDOException $e) {
    // Render visual premium alert if database server is offline
    echo "<div style='background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; padding: 20px; box-sizing: border-box;'>";
    echo "  <div style='background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 16px; padding: 32px; max-width: 500px; width: 100%; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); backdrop-filter: blur(10px);'>";
    echo "    <div style='color: #ef4444; font-size: 2.5rem; margin-bottom: 16px;'>⚠</div>";
    echo "    <h2 style='color: #f8fafc; font-size: 1.5rem; font-weight: 700; margin: 0 0 8px 0;'>Database Connection Offline</h2>";
    echo "    <p style='color: #94a3b8; font-size: 0.95rem; line-height: 1.6; margin: 0 0 24px 0;'>MySQL database server could not be reached. Please ensure your <strong>XAMPP / Laragon MySQL service</strong> is running and credentials are correct.</p>";
    echo "    <div style='background: rgba(15, 23, 42, 0.6); border-radius: 8px; padding: 12px; font-family: monospace; font-size: 0.85rem; color: #f1f5f9; overflow-x: auto; border: 1px solid rgba(255, 255, 255, 0.05);'>";
    echo "      <strong>Details:</strong> " . htmlspecialchars($e->getMessage());
    echo "    </div>";
    echo "  </div>";
    echo "</div>";
    exit;
}
?>

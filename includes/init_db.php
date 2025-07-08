<?php
/**
 * Database Initialization Script
 * Creates the database schema and initial data for new installations
 */

require_once __DIR__ . '/config.php';

function initializeDatabase() {
    global $pdo;
    
    try {
        // Read and execute schema
        $schemaFile = __DIR__ . '/../database/schema.sql';
        if (file_exists($schemaFile)) {
            $schema = file_get_contents($schemaFile);
            
            // Split into individual statements
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^(--|\/\*|START|SET|COMMIT)/', $statement)) {
                    $pdo->exec($statement);
                }
            }
            
            echo "Database schema initialized successfully.\n";
            return true;
        } else {
            echo "Schema file not found.\n";
            return false;
        }
    } catch (Exception $e) {
        echo "Database initialization failed: " . $e->getMessage() . "\n";
        return false;
    }
}

// Run initialization if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    if (initializeDatabase()) {
        echo "Database initialization completed successfully.\n";
    } else {
        echo "Database initialization failed.\n";
        exit(1);
    }
}
?>

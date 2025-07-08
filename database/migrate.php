<?php
/**
 * Database Migration Runner
 * Handles database schema updates and migrations
 */

require_once __DIR__ . '/../includes/config.php';

class DatabaseMigrator {
    private $pdo;
    private $migrationsTable = 'migrations';
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->initializeMigrationsTable();
    }
    
    private function initializeMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `migration` varchar(255) NOT NULL,
            `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
    }
    
    public function runMigrations() {
        $migrationsDir = __DIR__ . '/migrations';
        $migrationFiles = glob($migrationsDir . '/*.sql');
        
        sort($migrationFiles);
        
        foreach ($migrationFiles as $file) {
            $migrationName = basename($file);
            
            if (!$this->isMigrationExecuted($migrationName)) {
                $this->executeMigration($file, $migrationName);
            }
        }
    }
    
    private function isMigrationExecuted($migrationName) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$this->migrationsTable}` WHERE migration = ?");
        $stmt->execute([$migrationName]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function executeMigration($file, $migrationName) {
        try {
            $sql = file_get_contents($file);
            $this->pdo->exec($sql);
            
            $stmt = $this->pdo->prepare("INSERT INTO `{$this->migrationsTable}` (migration) VALUES (?)");
            $stmt->execute([$migrationName]);
            
            echo "Executed migration: $migrationName\n";
        } catch (Exception $e) {
            echo "Failed to execute migration $migrationName: " . $e->getMessage() . "\n";
        }
    }
}

// Run migrations if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $migrator = new DatabaseMigrator();
    $migrator->runMigrations();
    echo "Migration process completed.\n";
}
?>

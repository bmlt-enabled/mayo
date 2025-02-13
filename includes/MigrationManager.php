<?php
namespace BmltEnabled\Mayo;

class MigrationManager {
    private static $instance = null;
    private $migrations_path;
    private $wpdb;

    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->migrations_path = dirname(__DIR__) . '/migrations/';
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function runMigrations() {
        // Get all migration files
        $files = glob($this->migrations_path . '*.php');
        sort($files); // Sort by timestamp

        // Get already run migrations
        $ran = $this->getRanMigrations();
        $batch = $this->getNextBatchNumber();

        foreach ($files as $file) {
            $migrationName = basename($file, '.php');
            
            // Skip if already ran or if it's BaseMigration
            if (in_array($migrationName, $ran) || $migrationName === 'BaseMigration') {
                continue;
            }

            require_once $file;
            
            // Convert filename to class name
            $className = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $migrationName);
            $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $className)));
            $fullyQualifiedClassName = "Mayo\\Migrations\\$className";
            
            // Run migration
            if (class_exists($fullyQualifiedClassName)) {
                $migration = new $fullyQualifiedClassName();
                $migration->up();

                // Log successful migration
                $this->logMigration($migrationName, $batch);
            }
        }
    }

    private function getRanMigrations() {
        $table_name = $this->wpdb->prefix . 'mayo_migrations';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var(
            $this->wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        );
        
        if (!$table_exists) {
            return [];
        }

        return $this->wpdb->get_col("SELECT migration FROM $table_name");
    }

    private function getNextBatchNumber() {
        $table_name = $this->wpdb->prefix . 'mayo_migrations';
        
        // Check if table exists
        $table_exists = $this->wpdb->get_var(
            $this->wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        );
        
        if (!$table_exists) {
            return 1;
        }

        $batch = $this->wpdb->get_var("SELECT MAX(batch) FROM $table_name");
        return (int)$batch + 1;
    }

    private function logMigration($migration, $batch) {
        $table_name = $this->wpdb->prefix . 'mayo_migrations';
        $this->wpdb->insert(
            $table_name,
            [
                'migration' => $migration,
                'batch' => $batch
            ],
            ['%s', '%d']
        );
    }
} 
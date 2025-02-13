<?php
namespace Mayo\Migrations;

abstract class BaseMigration {
    protected $wpdb;
    protected $prefix;

    public function __construct() {
        global $wpdb;
        
        if (!defined('ABSPATH')) {
            require_once dirname(__DIR__) . '/../../wp-load.php';
        }

        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'mayo_';
    }

    /**
     * Run the migration
     */
    abstract public function up();

    /**
     * Reverse the migration
     */
    abstract public function down();

    /**
     * Helper method to create tables using WordPress's dbDelta
     */
    protected function createTable($table, $sql) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Add table prefix if not already included
        if (strpos($table, $this->prefix) !== 0) {
            $table = $this->prefix . $table;
        }

        $charset_collate = $this->wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table ($sql) $charset_collate;";
        
        dbDelta($sql);
    }

    /**
     * Helper method to drop tables
     */
    protected function dropTable($table) {
        // Add table prefix if not already included
        if (strpos($table, $this->prefix) !== 0) {
            $table = $this->prefix . $table;
        }

        $this->wpdb->query("DROP TABLE IF EXISTS $table");
    }
}
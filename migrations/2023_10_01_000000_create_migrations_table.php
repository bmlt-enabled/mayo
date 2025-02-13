<?php
namespace Mayo\Migrations;

class CreateMigrationsTable extends BaseMigration {
    public function up() {
        $this->createTable('migrations', "
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            migration varchar(255) NOT NULL,
            batch int(11) NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ");
    }

    public function down() {
        $this->dropTable('migrations');
    }
} 
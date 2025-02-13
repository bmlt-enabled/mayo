<?php
namespace Mayo\Migrations;

class CreateEventsTable extends BaseMigration {
    public function up() {
        $this->createTable('events', "
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_name varchar(255) NOT NULL,
            event_type varchar(255) NOT NULL,
            event_date date NOT NULL,
            event_start_time time NOT NULL,
            event_end_time time NOT NULL,
            recurring_schedule text,
            flyer_url varchar(255),
            status enum('pending','approved') DEFAULT 'pending',
            PRIMARY KEY  (id)
        ");
    }

    public function down() {
        $this->dropTable('events');
    }
}
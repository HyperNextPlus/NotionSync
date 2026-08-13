<?php

namespace Kanboard\Plugin\NotionSync\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE notionsync_settings (
            id INT NOT NULL AUTO_INCREMENT,
            api_token TEXT,
            tasks_database_id VARCHAR(64) DEFAULT '',
            updated_at INT DEFAULT 0,
            PRIMARY KEY(id)
        ) ENGINE=InnoDB CHARSET=utf8
    ");

    $pdo->exec("
        CREATE TABLE notionsync_fields (
            id INT NOT NULL AUTO_INCREMENT,
            project_id INT NOT NULL,
            property_name VARCHAR(255) NOT NULL,
            property_type VARCHAR(32) NOT NULL,
            value_template TEXT,
            relation_database_id VARCHAR(64) DEFAULT '',
            position INT DEFAULT 0,
            PRIMARY KEY(id),
            INDEX notionsync_fields_project_idx (project_id),
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARSET=utf8
    ");

    $pdo->exec("
        CREATE TABLE notionsync_delete_actions (
            project_id INT NOT NULL,
            property_name VARCHAR(255) DEFAULT '',
            property_type VARCHAR(32) DEFAULT 'status',
            property_value TEXT,
            PRIMARY KEY(project_id),
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARSET=utf8
    ");

    $pdo->exec("
        CREATE TABLE notionsync_pages (
            task_id INT NOT NULL,
            project_id INT NOT NULL,
            page_id VARCHAR(64) NOT NULL,
            created_at INT DEFAULT 0,
            PRIMARY KEY(task_id)
        ) ENGINE=InnoDB CHARSET=utf8
    ");

    $pdo->exec("
        CREATE TABLE notionsync_queue (
            id INT NOT NULL AUTO_INCREMENT,
            task_id INT NOT NULL,
            project_id INT NOT NULL,
            operation VARCHAR(16) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            snapshot TEXT,
            attempts INT DEFAULT 0,
            last_error TEXT,
            created_at INT DEFAULT 0,
            updated_at INT DEFAULT 0,
            PRIMARY KEY(id),
            INDEX notionsync_queue_status_idx (status),
            INDEX notionsync_queue_task_idx (task_id)
        ) ENGINE=InnoDB CHARSET=utf8
    ");
}

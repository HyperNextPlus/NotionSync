<?php

namespace Kanboard\Plugin\NotionSync\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE notionsync_settings (
            id SERIAL PRIMARY KEY,
            api_token TEXT DEFAULT '',
            tasks_database_id VARCHAR(64) DEFAULT '',
            updated_at INTEGER DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE TABLE notionsync_fields (
            id SERIAL PRIMARY KEY,
            project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
            property_name VARCHAR(255) NOT NULL,
            property_type VARCHAR(32) NOT NULL,
            value_template TEXT DEFAULT '',
            relation_database_id VARCHAR(64) DEFAULT '',
            position INTEGER DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE TABLE notionsync_delete_actions (
            project_id INTEGER PRIMARY KEY REFERENCES projects(id) ON DELETE CASCADE,
            property_name VARCHAR(255) DEFAULT '',
            property_type VARCHAR(32) DEFAULT 'status',
            property_value TEXT DEFAULT ''
        )
    ");

    $pdo->exec("
        CREATE TABLE notionsync_pages (
            task_id INTEGER PRIMARY KEY,
            project_id INTEGER NOT NULL,
            page_id VARCHAR(64) NOT NULL,
            created_at INTEGER DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE TABLE notionsync_queue (
            id SERIAL PRIMARY KEY,
            task_id INTEGER NOT NULL,
            project_id INTEGER NOT NULL,
            operation VARCHAR(16) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            snapshot TEXT DEFAULT '',
            attempts INTEGER DEFAULT 0,
            last_error TEXT DEFAULT '',
            created_at INTEGER DEFAULT 0,
            updated_at INTEGER DEFAULT 0
        )
    ");

    $pdo->exec("CREATE INDEX notionsync_queue_status_idx ON notionsync_queue(status)");
    $pdo->exec("CREATE INDEX notionsync_queue_task_idx ON notionsync_queue(task_id)");
    $pdo->exec("CREATE INDEX notionsync_fields_project_idx ON notionsync_fields(project_id)");
}

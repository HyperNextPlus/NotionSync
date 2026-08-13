<?php

namespace Kanboard\Plugin\NotionSync\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE notionsync_settings (
            id INTEGER PRIMARY KEY,
            api_token TEXT DEFAULT '',
            tasks_database_id TEXT DEFAULT '',
            updated_at INTEGER DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE TABLE notionsync_fields (
            id INTEGER PRIMARY KEY,
            project_id INTEGER NOT NULL,
            property_name TEXT NOT NULL,
            property_type TEXT NOT NULL,
            value_template TEXT DEFAULT '',
            relation_database_id TEXT DEFAULT '',
            position INTEGER DEFAULT 0,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE notionsync_delete_actions (
            project_id INTEGER PRIMARY KEY,
            property_name TEXT DEFAULT '',
            property_type TEXT DEFAULT 'status',
            property_value TEXT DEFAULT '',
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE notionsync_pages (
            task_id INTEGER PRIMARY KEY,
            project_id INTEGER NOT NULL,
            page_id TEXT NOT NULL,
            created_at INTEGER DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE TABLE notionsync_queue (
            id INTEGER PRIMARY KEY,
            task_id INTEGER NOT NULL,
            project_id INTEGER NOT NULL,
            operation TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
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

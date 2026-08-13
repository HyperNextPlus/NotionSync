<?php if ($notion_has_fields && ! empty($notion_job)): ?>
    <?php if ($notion_has_unfinished): ?>
        <div class="alert alert-error">
            <p>
                <strong><?= t('Notion synchronization pending') ?></strong>
            </p>
            <?php if (! empty($notion_job['last_error'])): ?>
                <p><?= $this->text->e($notion_job['last_error']) ?></p>
            <?php else: ?>
                <p><?= t('This task has not been sent to Notion yet.') ?></p>
            <?php endif ?>
            <p>
                <?= $this->url->link(
                    t('Retry synchronization'),
                    'NotionTaskController',
                    'retry',
                    array('task_id' => $task['id'], 'project_id' => $task['project_id'], 'plugin' => 'NotionSync'),
                    true,
                    'btn btn-blue'
                ) ?>
            </p>
        </div>
    <?php endif ?>
<?php endif ?>

<?php if ($this->user->hasProjectAccess('NotionProjectController', 'show', $project['id'])): ?>
    <li <?= $this->app->checkMenuSelection('NotionProjectController', 'show') ?>>
        <?= $this->url->link(t('Notion synchronization'), 'NotionProjectController', 'show', array('project_id' => $project['id'], 'plugin' => 'NotionSync')) ?>
    </li>
<?php endif ?>

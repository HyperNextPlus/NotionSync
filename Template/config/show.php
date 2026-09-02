<div class="page-header">
    <h2><?= t('NotionSync settings') ?></h2>
</div>

<?php if (! $is_configured): ?>
    <div class="alert alert-error">
        <?= t('The plugin is not configured yet: no task will be synchronized with Notion until you complete these values.') ?>
    </div>
<?php endif ?>

<?php if ($has_plaintext_token): ?>
    <div class="alert alert-error">
        <?= t('The stored token is not encrypted yet. Save this form again to encrypt it.') ?>
    </div>
<?php endif ?>

<form method="post" action="<?= $this->url->href('NotionConfigController', 'save', array('plugin' => 'NotionSync')) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <fieldset>
        <?= $this->form->label(t('Notion integration token'), 'api_token') ?>
        <?= $this->form->password('api_token', $values, $errors) ?>
        <p class="form-help">
            <?php if ($has_token): ?>
                <?= t('A token is already saved. Leave this field empty to keep it.') ?>
            <?php else: ?>
                <?= t('Secret token of the internal integration created in Notion.') ?>
            <?php endif ?>
        </p>

        <?= $this->form->label(t('Identifier of the "Tasks" database'), 'tasks_database_id') ?>
        <?= $this->form->text('tasks_database_id', $values, $errors) ?>
        <p class="form-help"><?= t('The database_id of the database where task pages will be created.') ?></p>
    </fieldset>

    <div class="alert">
        <p><?= t('Remember to share the "Tasks" database (and any related database used by relation fields) with the Notion integration. Without that permission the API replies that the database cannot be found.') ?></p>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
    </div>
</form>

<div class="page-header margin-top">
    <h2><?= t('Queue processing') ?></h2>
</div>

<div class="panel">
    <p><?= t('The plugin only queues changes: the calls to Notion are made by the queue processor, which has to be triggered periodically.') ?></p>

    <h3><?= t('From the command line') ?></h3>
    <pre>*/5 * * * * cd <?= $this->text->e(realpath(ROOT_DIR)) ?> &amp;&amp; ./cli notionsync:process-queue &gt;/dev/null 2&gt;&amp;1</pre>

    <h3><?= t('From a URL') ?></h3>
    <p><?= t('For hosting providers that do not allow running commands from the command line. Both forms are equivalent; the second one does not require URL rewriting.') ?></p>

    <?php if ($has_webhook_token): ?>
        <pre><?= $this->text->e($cron_pretty_url) ?></pre>
        <pre><?= $this->text->e($cron_query_url) ?></pre>
        <p class="form-help">
            <?= t('The token is the global webhook token of Kanboard, in Settings &gt; Webhooks. Resetting it there also invalidates these URLs.') ?>
        </p>
        <p class="form-help">
            <?= t('Optional parameters: &limit=N (jobs per run, 20 by default) and &delay=N (pause in milliseconds between calls to Notion, 350 by default).') ?>
        </p>
    <?php else: ?>
        <div class="alert alert-error">
            <?= t('This instance has no webhook token, so the URL is disabled. Generate one in Settings &gt; Webhooks.') ?>
        </div>
    <?php endif ?>
</div>

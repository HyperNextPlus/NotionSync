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

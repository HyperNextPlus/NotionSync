<div class="page-header">
    <h2><?= t('Notion synchronization') ?></h2>
</div>

<?php if (! $is_configured): ?>
    <div class="alert alert-error">
        <?= t('The global plugin settings are missing (token and database). An administrator must complete them for synchronization to work.') ?>
    </div>
<?php endif ?>

<?php if (empty($fields)): ?>
    <div class="alert">
        <?= t('This project has no property configured, so its tasks are not synchronized with Notion. Add at least one property to enable synchronization.') ?>
    </div>
<?php endif ?>

<div class="panel">
    <h3><?= t('Variables available in templates') ?></h3>
    <ul>
        <?php foreach ($variables as $variable => $description): ?>
            <li><code><?= $this->text->e($variable) ?></code> &mdash; <?= $this->text->e($description) ?></li>
        <?php endforeach ?>
    </ul>
    <p class="form-help"><?= t('You can combine free text and variables, for example: Task {{task_id}} of {{project_name}}') ?></p>
</div>

<?php if (! empty($fields)): ?>
    <h3><?= t('Synchronized properties') ?></h3>

    <?php foreach ($fields as $field): ?>
        <form method="post" class="panel" action="<?= $this->url->href('NotionProjectController', 'updateField', array('project_id' => $project['id'], 'field_id' => $field['id'], 'plugin' => 'NotionSync')) ?>" autocomplete="off">
            <?= $this->form->csrf() ?>

            <?= $this->form->label(t('Notion property name'), 'property_name') ?>
            <?= $this->form->text('property_name', $field, $errors) ?>

            <?= $this->form->label(t('Property type'), 'property_type') ?>
            <?= $this->form->select('property_type', $types, $field, $errors) ?>

            <?= $this->form->label(t('Value template'), 'value_template') ?>
            <?= $this->form->textarea('value_template', $field, $errors) ?>

            <?= $this->form->label(t('Related database (relation type only)'), 'relation_database_id') ?>
            <?= $this->form->text('relation_database_id', $field, $errors) ?>
            <p class="form-help"><?= t('The database_id of the database where the page whose title exactly matches the resolved value will be searched.') ?></p>

            <div class="form-actions">
                <button type="submit" class="btn btn-blue"><?= t('Update') ?></button>
                <?= $this->url->link(t('Remove'), 'NotionProjectController', 'removeField', array('project_id' => $project['id'], 'field_id' => $field['id'], 'plugin' => 'NotionSync'), true, 'btn btn-red') ?>
            </div>
        </form>
    <?php endforeach ?>
<?php endif ?>

<h3><?= t('Add a property') ?></h3>

<form method="post" action="<?= $this->url->href('NotionProjectController', 'saveField', array('project_id' => $project['id'], 'plugin' => 'NotionSync')) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <fieldset>
        <?= $this->form->label(t('Notion property name'), 'property_name') ?>
        <?= $this->form->text('property_name', $values, $errors) ?>
        <p class="form-help"><?= t('It must match exactly the property name in the Notion database.') ?></p>

        <?= $this->form->label(t('Property type'), 'property_type') ?>
        <?= $this->form->select('property_type', $types, $values, $errors) ?>

        <?= $this->form->label(t('Value template'), 'value_template') ?>
        <?= $this->form->textarea('value_template', $values, $errors) ?>
        <p class="form-help"><?= t('For a multi-select property, separate the values with commas.') ?></p>

        <?= $this->form->label(t('Related database (relation type only)'), 'relation_database_id') ?>
        <?= $this->form->text('relation_database_id', $values, $errors) ?>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-blue"><?= t('Add') ?></button>
    </div>
</form>

<h3><?= t('When a task is removed') ?></h3>

<form method="post" action="<?= $this->url->href('NotionProjectController', 'saveDeleteAction', array('project_id' => $project['id'], 'plugin' => 'NotionSync')) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <fieldset>
        <p class="form-help"><?= t('When a task is removed in Kanboard, its Notion page is neither deleted nor archived: only the property set here is updated.') ?></p>

        <?= $this->form->label(t('Property to update'), 'property_name') ?>
        <?= $this->form->text('property_name', $delete_action, $errors) ?>

        <?= $this->form->label(t('Property type'), 'property_type') ?>
        <?= $this->form->select('property_type', $types, $delete_action, $errors) ?>

        <?= $this->form->label(t('Value to assign'), 'property_value') ?>
        <?= $this->form->text('property_value', $delete_action, $errors) ?>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
    </div>
</form>

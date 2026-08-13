<li <?= $this->app->checkMenuSelection('NotionConfigController', 'show') ?>>
    <?= $this->url->link('NotionSync', 'NotionConfigController', 'show', array('plugin' => 'NotionSync')) ?>
</li>

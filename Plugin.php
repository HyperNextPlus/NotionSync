<?php

namespace Kanboard\Plugin\NotionSync;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Core\Translator;
use Kanboard\Model\TaskModel as CoreTaskModel;
use Kanboard\Plugin\NotionSync\Console\ProcessQueueCommand;
use Kanboard\Plugin\NotionSync\Model\DeleteActionModel;
use Kanboard\Plugin\NotionSync\Model\FieldModel;
use Kanboard\Plugin\NotionSync\Model\PageModel;
use Kanboard\Plugin\NotionSync\Model\QueueModel;
use Kanboard\Plugin\NotionSync\Model\SettingsModel;
use Kanboard\Plugin\NotionSync\Model\TaskModel;
use Kanboard\Plugin\NotionSync\Service\HttpClient;
use Kanboard\Plugin\NotionSync\Service\NotionApiClient;
use Kanboard\Plugin\NotionSync\Service\SyncService;
use Kanboard\Plugin\NotionSync\Service\TemplateResolver;
use Kanboard\Plugin\NotionSync\Service\TokenCipher;
use Kanboard\Plugin\NotionSync\Subscriber\TaskSubscriber;

class Plugin extends Base
{
    public function initialize()
    {
        $this->registerServices();
        $this->registerRoutes();
        $this->registerAccessControl();
        $this->registerListeners();
        $this->registerTemplateHooks();

        $this->cli->add(new ProcessQueueCommand($this->container));
    }

    /**
     * Sustituye el TaskModel del core por el del plugin.
     *
     * Es la única forma de enterarse de que una tarea fue eliminada: el modelo
     * del core no emite ningún evento en remove(). Se hace vía getClasses() y no
     * en initialize() porque el contenedor se construye antes, y así el override
     * está en pie desde la primera petición.
     *
     * @return array
     */
    public function getClasses()
    {
        return array(
            'Plugin\NotionSync\Model' => array(
                'TaskModel',
            ),
        );
    }

    /**
     * Servicios propios, con prefijo "notion" para no chocar con las claves del
     * contenedor del core.
     */
    private function registerServices()
    {
        $this->container['notionSettingsModel'] = function ($c) {
            return new SettingsModel($c);
        };

        $this->container['notionFieldModel'] = function ($c) {
            return new FieldModel($c);
        };

        $this->container['notionDeleteActionModel'] = function ($c) {
            return new DeleteActionModel($c);
        };

        $this->container['notionPageModel'] = function ($c) {
            return new PageModel($c);
        };

        $this->container['notionQueueModel'] = function ($c) {
            return new QueueModel($c);
        };

        $this->container['notionTokenCipher'] = function ($c) {
            return new TokenCipher($c);
        };

        $this->container['notionHttpClient'] = function ($c) {
            return new HttpClient($c);
        };

        $this->container['notionApiClient'] = function ($c) {
            return new NotionApiClient($c);
        };

        $this->container['notionTemplateResolver'] = function ($c) {
            return new TemplateResolver($c);
        };

        $this->container['notionSyncService'] = function ($c) {
            return new SyncService($c);
        };
    }

    private function registerRoutes()
    {
        $this->route->addRoute('/notionsync/settings', 'NotionConfigController', 'show', 'NotionSync');
        $this->route->addRoute('/notionsync/project/:project_id', 'NotionProjectController', 'show', 'NotionSync');
        $this->route->addRoute('/notionsync/cron', 'NotionCronjobController', 'run', 'NotionSync');
    }

    /**
     * La configuración global es de instancia, así que solo la toca un
     * administrador. El mapeo de campos vive dentro de un proyecto y sigue la
     * convención de la pantalla "Integraciones" del core: manager del proyecto.
     * El reintento manual queda al alcance de cualquiera que pueda ver la tarea.
     * El cron por URL es la excepción: es público y su control de acceso es el token, no un rol.
     */
    private function registerAccessControl()
    {
        $this->applicationAccessMap->add('NotionConfigController', '*', Role::APP_ADMIN);
        $this->projectAccessMap->add('NotionProjectController', '*', Role::PROJECT_MANAGER);
        $this->projectAccessMap->add('NotionTaskController', '*', Role::PROJECT_VIEWER);
        $this->applicationAccessMap->add('NotionCronjobController', array('run'), Role::APP_PUBLIC);
    }

    private function registerListeners()
    {
        $subscriber = new TaskSubscriber($this->container);

        $this->dispatcher->addListener(CoreTaskModel::EVENT_CREATE, array($subscriber, 'onTaskCreate'));
        $this->dispatcher->addListener(CoreTaskModel::EVENT_UPDATE, array($subscriber, 'onTaskUpdate'));
        $this->dispatcher->addListener(TaskModel::EVENT_DELETE, array($subscriber, 'onTaskDelete'));
    }

    private function registerTemplateHooks()
    {
        $this->template->hook->attach('template:config:sidebar', 'NotionSync:config/sidebar');
        $this->template->hook->attach('template:project:sidebar', 'NotionSync:project/sidebar');

        // El estado de sincronización depende de la tarea que se está viendo, así
        // que se resuelve en el momento del render y no en el arranque.
        $this->template->hook->attachCallable(
            'template:task:details:top',
            'NotionSync:task/sync_status',
            function ($task) {
                return array(
                    'notion_job' => $this->queueModelForTemplate()->getLastByTask($task['id']),
                    'notion_has_unfinished' => $this->queueModelForTemplate()->hasUnfinished($task['id']),
                    'notion_has_fields' => $this->fieldModelForTemplate()->hasFields($task['project_id']),
                );
            }
        );
    }

    /**
     * @return QueueModel
     */
    private function queueModelForTemplate()
    {
        return $this->container['notionQueueModel'];
    }

    /**
     * @return FieldModel
     */
    private function fieldModelForTemplate()
    {
        return $this->container['notionFieldModel'];
    }

    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');
    }

    public function getPluginName()
    {
        return 'NotionSync';
    }

    public function getPluginDescription()
    {
        return t('Creates and keeps Kanboard tasks synchronized as pages in a Notion database.');
    }

    public function getPluginAuthor()
    {
        return 'HyperNextPlus SAS';
    }

    public function getPluginVersion()
    {
        return '0.0.2';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/HyperNextPlus/NotionSync';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.0';
    }
}

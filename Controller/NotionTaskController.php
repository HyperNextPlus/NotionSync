<?php

namespace Kanboard\Plugin\NotionSync\Controller;

use Kanboard\Controller\BaseController;

/**
 * Reintento manual desde la vista de la tarea.
 *
 * A diferencia del cron, aquí la llamada a Notion es síncrona: el usuario
 * necesita ver el resultado en el momento (reglas 11, escenarios 8 y 9).
 */
class NotionTaskController extends BaseController
{
    public function retry()
    {
        $task = $this->getTask();
        $this->checkCSRFParam();

        $jobs = $this->notionQueueModel->getUnfinishedByTask($task['id']);

        if (empty($jobs)) {
            $this->flash->failure(t('This task has no pending synchronization.'));
            $this->redirectToTask($task);

            return;
        }

        $failed = 0;

        foreach ($jobs as $job) {
            if (! $this->notionSyncService->processJob($job)) {
                $failed++;
            }
        }

        if ($failed === 0) {
            $this->flash->success(t('Synchronization with Notion completed.'));
        } else {
            $last = $this->notionQueueModel->getLastByTask($task['id']);
            $this->flash->failure(t('Synchronization with Notion failed: %s', $last['last_error']));
        }

        $this->redirectToTask($task);
    }

    private function redirectToTask(array $task)
    {
        $this->response->redirect($this->helper->url->to('TaskViewController', 'show', array(
            'task_id' => $task['id'],
            'project_id' => $task['project_id'],
        )));
    }
}

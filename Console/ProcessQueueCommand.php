<?php

namespace Kanboard\Plugin\NotionSync\Console;

use Kanboard\Console\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Procesa la cola de sincronizaciones pendientes.
 *
 * Pensado para ejecutarse desde el cron del sistema operativo:
 *
 *   * * * * * cd /ruta/a/kanboard && ./cli notionsync:process-queue >/dev/null 2>&1
 *
 * La frecuencia queda a criterio del despliegue. El límite de trabajos por
 * ejecución y la pausa entre llamadas permiten no agotar el límite de peticiones
 * por segundo de Notion cuando se acumulan muchas tareas.
 */
class ProcessQueueCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('notionsync:process-queue')
            ->setDescription('Process the pending sync queue with Notion')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of jobs to process in this run', 50)
            ->addOption('delay', 'd', InputOption::VALUE_REQUIRED, 'Pause in milliseconds between API calls', 350)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->notionSettingsModel->isConfigured()) {
            $output->writeln('<error>'.t('NotionSync is not configured: the token or the database identifier is missing.').'</error>');

            return 1;
        }

        $limit = max(1, (int) $input->getOption('limit'));
        $delay = max(0, (int) $input->getOption('delay')) * 1000;
        $jobs = $this->notionQueueModel->getUnfinished($limit);

        if (empty($jobs)) {
            $output->writeln(t('No pending synchronizations.'));

            return 0;
        }

        $synced = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            if ($this->notionSyncService->processJob($job)) {
                $synced++;
                $output->writeln(sprintf('<info>OK</info> trabajo #%d (%s) tarea #%d', $job['id'], $job['operation'], $job['task_id']));
            } else {
                $failed++;
                $last = $this->notionQueueModel->getLastByTask($job['task_id']);
                $output->writeln(sprintf(
                    '<error>ERROR</error> trabajo #%d (%s) tarea #%d: %s',
                    $job['id'],
                    $job['operation'],
                    $job['task_id'],
                    isset($last['last_error']) ? $last['last_error'] : ''
                ));
            }

            if ($delay > 0) {
                usleep($delay);
            }
        }

        $output->writeln(t('Processed: %d | Synced: %d | Failed: %d', count($jobs), $synced, $failed));

        return $failed > 0 ? 1 : 0;
    }
}

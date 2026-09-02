<?php

namespace Kanboard\Plugin\NotionSync\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Ejecuta la cola de sincronización desde una URL.
 *
 * Es el equivalente de `./cli notionsync:process-queue` para los alojamientos
 * que no permiten ejecutar procesos por consola. Sigue el mismo patrón que
 * CronjobController del core: ruta pública, autenticada con el `webhook_token`
 * global de Kanboard, que despacha el comando de Symfony Console en lugar de
 * duplicar su lógica.
 *
 *   wget -q -O - "https://ejemplo.com/notionsync/cron?token=TOKEN"
 *
 * A diferencia del core, la salida del comando se devuelve en el cuerpo de la
 * respuesta: un cron por URL no tiene stdout donde mirar y ese texto es lo único
 * que queda en el log del servicio que dispara la petición.
 */
class NotionCronjobController extends BaseController
{
    /**
     * Se procesan menos trabajos por ejecución que en la CLI.
     *
     * La consola no tiene límite de tiempo, pero una petición HTTP sí:
     * `max_execution_time` suele estar en 30 s en los alojamientos compartidos,
     * que son justamente los que obligan a usar esta ruta. Con estos valores la
     * pausa acumulada es de 7 s y quedan unos 20 s para las llamadas a Notion.
     */
    const DEFAULT_LIMIT = 20;

    /**
     * Misma pausa que la CLI: es un límite de Notion, no del entorno.
     */
    const DEFAULT_DELAY = 350;

    /**
     * Techo del parámetro `limit` de la URL.
     */
    const MAX_LIMIT = 500;

    public function run()
    {
        $this->checkCronjobToken();

        // Cada trabajo se confirma en la base en cuanto termina, así que si la
        // petición muere por tiempo de ejecución no se pierde nada: lo ya
        // sincronizado queda hecho y el resto lo toma la siguiente pasada.
        // Aun así se intenta levantar el límite, que es gratis cuando el
        // alojamiento lo permite.
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $input = new ArrayInput(array(
            'command' => 'notionsync:process-queue',
            '--limit' => (string) $this->getLimit(),
            '--delay' => (string) $this->getDelay(),
        ));

        // decorated = false: sin códigos de color ANSI, que en un navegador o en
        // el log de un servicio de cron solo serían ruido.
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

        $this->cli->setAutoExit(false);
        $this->cli->run($input, $output);

        // Siempre 200 mientras el endpoint haya podido ejecutarse. Un trabajo
        // fallido no es un fallo de la petición: se reintenta solo en la
        // siguiente pasada, y marcarlo como error HTTP haría que cualquier
        // incidencia pasajera de Notion disparase las alertas del monitor. El
        // detalle va en el cuerpo, en la línea "Processed | Synced | Failed".
        $this->response->text($output->fetch());
    }

    /**
     * Igual que BaseController::checkWebhookToken(), pero rechazando además el
     * caso en que la instancia no tenga `webhook_token`: sin esta guarda,
     * hash_equals('', '') daría por buena una petición sin token y dejaría el
     * endpoint abierto.
     *
     * @throws AccessForbiddenException
     */
    private function checkCronjobToken()
    {
        $expected = $this->configModel->get('webhook_token');

        if ($expected === '' || $expected === null) {
            throw AccessForbiddenException::getInstance()->withoutLayout();
        }

        $this->checkWebhookToken();
    }

    /**
     * @return int
     */
    private function getLimit()
    {
        $limit = $this->request->getIntegerParam('limit', self::DEFAULT_LIMIT);

        return min(max($limit, 1), self::MAX_LIMIT);
    }

    /**
     * @return int
     */
    private function getDelay()
    {
        return max($this->request->getIntegerParam('delay', self::DEFAULT_DELAY), 0);
    }
}

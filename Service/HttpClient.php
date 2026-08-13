<?php

namespace Kanboard\Plugin\NotionSync\Service;

use Kanboard\Core\Base;
use Kanboard\Plugin\NotionSync\Exception\NotionApiException;

/**
 * Cliente HTTP mínimo para la API de Notion.
 *
 * No se reutiliza Kanboard\Core\Http\Client porque su implementación con cURL
 * solo contempla GET, POST y PUT: un PATCH degradaría silenciosamente a GET, y
 * la API de Notion usa PATCH para actualizar páginas y bases de datos. Ese
 * cliente tampoco expone el código de estado ni el cuerpo del error, que aquí se
 * necesitan para explicarle al usuario por qué falló la sincronización.
 *
 * Se respetan los ajustes globales de red de Kanboard (timeout, proxy,
 * verificación SSL) para que el plugin funcione igual que el resto de la
 * aplicación en instalaciones detrás de proxy.
 */
class HttpClient extends Base
{
    const USER_AGENT = 'Kanboard-NotionSync';

    /**
     * Ejecuta una petición y devuelve la respuesta decodificada.
     *
     * @param  string $method  GET, POST o PATCH
     * @param  string $url
     * @param  array  $headers
     * @param  array  $payload Cuerpo que se enviará como JSON
     * @return array
     * @throws NotionApiException
     */
    public function request($method, $url, array $headers, array $payload = array())
    {
        if (! function_exists('curl_init')) {
            throw new NotionApiException(t('The PHP cURL extension is required to connect to Notion.'));
        }

        $body = empty($payload) ? '' : json_encode($payload);
        $session = curl_init();

        curl_setopt($session, CURLOPT_URL, $url);
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($session, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($session, CURLOPT_TIMEOUT, HTTP_TIMEOUT);
        curl_setopt($session, CURLOPT_CONNECTTIMEOUT, HTTP_TIMEOUT);

        if ($body !== '') {
            curl_setopt($session, CURLOPT_POSTFIELDS, $body);
            $headers[] = 'Content-Length: '.strlen($body);
        }

        curl_setopt($session, CURLOPT_HTTPHEADER, $headers);

        if (HTTP_VERIFY_SSL_CERTIFICATE === false) {
            curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        }

        if (HTTP_PROXY_HOSTNAME) {
            curl_setopt($session, CURLOPT_PROXY, HTTP_PROXY_HOSTNAME);
            curl_setopt($session, CURLOPT_PROXYPORT, HTTP_PROXY_PORT);
            curl_setopt($session, CURLOPT_NOPROXY, HTTP_PROXY_EXCLUDE);

            if (HTTP_PROXY_USERNAME) {
                curl_setopt($session, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
                curl_setopt($session, CURLOPT_PROXYUSERPWD, HTTP_PROXY_USERNAME.':'.HTTP_PROXY_PASSWORD);
            }
        }

        $response = curl_exec($session);

        if ($response === false) {
            $error = curl_error($session);
            curl_close($session);
            $this->logger->error('NotionSync: '.$method.' '.$url.' => '.$error);

            throw new NotionApiException(t('Unable to reach the Notion API: %s', $error));
        }

        $statusCode = (int) curl_getinfo($session, CURLINFO_RESPONSE_CODE);
        curl_close($session);

        $decoded = json_decode($response, true);

        if (! is_array($decoded)) {
            $decoded = array();
        }

        if ($statusCode >= 400) {
            throw new NotionApiException($this->formatError($statusCode, $decoded), $statusCode);
        }

        return $decoded;
    }

    /**
     * Convierte la respuesta de error de Notion en un mensaje legible.
     *
     * @param  integer $statusCode
     * @param  array   $decoded
     * @return string
     */
    private function formatError($statusCode, array $decoded)
    {
        $message = isset($decoded['message']) ? $decoded['message'] : t('no details provided');
        $code = isset($decoded['code']) ? $decoded['code'] : '';

        if ($code !== '') {
            return sprintf('HTTP %d (%s): %s', $statusCode, $code, $message);
        }

        return sprintf('HTTP %d: %s', $statusCode, $message);
    }
}

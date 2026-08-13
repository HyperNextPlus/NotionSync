<?php

namespace Kanboard\Plugin\NotionSync\Service;

use Kanboard\Core\Base;
use Kanboard\Plugin\NotionSync\Exception\SyncException;

/**
 * Traducción entre el plugin y la API REST de Notion.
 *
 * Esta clase concentra todo el conocimiento sobre endpoints y formato de
 * propiedades, de modo que el resto del plugin trabaja con arrays de Kanboard y
 * no necesita saber nada de Notion.
 *
 * Se fija la versión 2022-06-28 de la API, que es la que usa "parent.database_id"
 * para crear páginas. Las versiones a partir de 2025 reemplazan ese concepto por
 * "data sources" y cambian la forma del parent, así que subir de versión no es
 * un cambio transparente.
 */
class NotionApiClient extends Base
{
    const API_URL = 'https://api.notion.com/v1/';
    const API_VERSION = '2022-06-28';

    /**
     * Límites documentados por Notion. Se validan antes de enviar para poder dar
     * un mensaje entendible en lugar de un HTTP 400 genérico.
     */
    const MAX_TEXT_LENGTH = 2000;
    const MAX_OPTION_LENGTH = 100;
    const MAX_ARRAY_ITEMS = 100;

    /**
     * Tope de páginas a recorrer al resolver una relación, para que una base
     * relacionada enorme no bloquee la ejecución del cron.
     */
    const MAX_QUERY_PAGES = 20;
    const QUERY_PAGE_SIZE = 100;

    /**
     * Cache del nombre de la propiedad título por base de datos, válida durante
     * la ejecución actual.
     *
     * @var array
     */
    private $titlePropertyNames = array();

    /**
     * Crea una página en la base de datos indicada.
     *
     * POST https://api.notion.com/v1/pages
     *
     * @param  string $databaseId
     * @param  array  $properties Propiedades ya construidas con buildProperty()
     * @return string page_id de la página creada
     * @throws SyncException
     */
    public function createPage($databaseId, array $properties)
    {
        $response = $this->notionHttpClient->request('POST', self::API_URL.'pages', $this->getHeaders(), array(
            'parent' => array('database_id' => $databaseId),
            'properties' => $properties,
        ));

        if (empty($response['id'])) {
            throw new SyncException(t('Notion did not return the identifier of the created page.'));
        }

        return $response['id'];
    }

    /**
     * Actualiza una única propiedad de una página existente.
     *
     * PATCH https://api.notion.com/v1/pages/{page_id}
     *
     * Las propiedades que no viajan en el cuerpo quedan intactas, que es
     * justamente lo que exigen las reglas 6 y 7: al editar solo se toca el
     * título, y al eliminar solo la propiedad configurada.
     *
     * @param  string $pageId
     * @param  string $propertyName
     * @param  array  $propertyValue
     * @throws SyncException
     */
    public function updatePageProperty($pageId, $propertyName, array $propertyValue)
    {
        $this->notionHttpClient->request(
            'PATCH',
            self::API_URL.'pages/'.rawurlencode($pageId),
            $this->getHeaders(),
            array('properties' => array($propertyName => $propertyValue))
        );
    }

    /**
     * Busca en la base relacionada la página cuyo título coincide exactamente
     * con el valor dado.
     *
     * El filtro de Notion acota la búsqueda, pero la comparación final se hace
     * aquí con una igualdad estricta de PHP: la regla 5 exige una coincidencia
     * exacta, sensible a mayúsculas y espacios, y el operador "equals" de la API
     * no garantiza ese criterio.
     *
     * @param  string $databaseId
     * @param  string $title
     * @return string page_id de la única coincidencia
     * @throws SyncException Si no hay coincidencias o si hay más de una
     */
    public function findPageIdByExactTitle($databaseId, $title)
    {
        if ($title === '') {
            throw new SyncException(t('The template of a relation field resolved to an empty value.'));
        }

        $propertyName = $this->getTitlePropertyName($databaseId);
        $matches = array();
        $cursor = null;
        $pages = 0;

        do {
            $payload = array(
                'filter' => array(
                    'property' => $propertyName,
                    'title' => array('equals' => $title),
                ),
                'page_size' => self::QUERY_PAGE_SIZE,
            );

            if ($cursor !== null) {
                $payload['start_cursor'] = $cursor;
            }

            $response = $this->notionHttpClient->request(
                'POST',
                self::API_URL.'databases/'.rawurlencode($databaseId).'/query',
                $this->getHeaders(),
                $payload
            );

            foreach (isset($response['results']) ? $response['results'] : array() as $page) {
                if (isset($page['id']) && $this->extractPageTitle($page) === $title) {
                    $matches[] = $page['id'];
                }
            }

            $cursor = ! empty($response['has_more']) && ! empty($response['next_cursor']) ? $response['next_cursor'] : null;
            $pages++;
        } while ($cursor !== null && $pages < self::MAX_QUERY_PAGES);

        $matches = array_unique($matches);

        if (count($matches) === 1) {
            return reset($matches);
        }

        if (empty($matches)) {
            throw new SyncException(t('No page titled "%s" was found in the related database.', $title));
        }

        throw new SyncException(t('The title "%s" matches %d pages in the related database, so the relation is ambiguous.', $title, count($matches)));
    }

    /**
     * Nombre de la propiedad de tipo title de una base de datos.
     *
     * GET https://api.notion.com/v1/databases/{database_id}
     *
     * Se descubre en lugar de pedírselo al usuario: cada base puede llamar a su
     * columna principal como quiera, y es la única propiedad de tipo title.
     *
     * @param  string $databaseId
     * @return string
     * @throws SyncException
     */
    public function getTitlePropertyName($databaseId)
    {
        if (isset($this->titlePropertyNames[$databaseId])) {
            return $this->titlePropertyNames[$databaseId];
        }

        $response = $this->notionHttpClient->request(
            'GET',
            self::API_URL.'databases/'.rawurlencode($databaseId),
            $this->getHeaders()
        );

        foreach (isset($response['properties']) ? $response['properties'] : array() as $name => $property) {
            if (isset($property['type']) && $property['type'] === 'title') {
                $this->titlePropertyNames[$databaseId] = $name;

                return $name;
            }
        }

        throw new SyncException(t('The related database has no property of type title.'));
    }

    /**
     * Reconstruye el texto plano del título de una página devuelta por la API.
     *
     * @param  array $page
     * @return string
     */
    private function extractPageTitle(array $page)
    {
        if (empty($page['properties'])) {
            return '';
        }

        foreach ($page['properties'] as $property) {
            if (! isset($property['type']) || $property['type'] !== 'title') {
                continue;
            }

            $title = '';

            foreach (isset($property['title']) ? $property['title'] : array() as $fragment) {
                if (isset($fragment['plain_text'])) {
                    $title .= $fragment['plain_text'];
                }
            }

            return $title;
        }

        return '';
    }

    /**
     * Construye el valor de una propiedad según su tipo.
     *
     * Un valor vacío se envía como null, que en Notion significa "sin valor":
     * enviar un nombre de opción vacío o una fecha vacía provoca un error 400.
     *
     * @param  string $type
     * @param  string $value Resultado de resolver la plantilla del campo
     * @return array
     * @throws SyncException
     */
    public function buildProperty($type, $value)
    {
        switch ($type) {
            case 'title':
                $this->assertTextLength($value);

                return array('title' => $value === '' ? array() : array(array('text' => array('content' => $value))));

            case 'rich_text':
                $this->assertTextLength($value);

                return array('rich_text' => $value === '' ? array() : array(array('text' => array('content' => $value))));

            case 'select':
                $this->assertOptionLength($value);

                return array('select' => $value === '' ? null : array('name' => $value));

            case 'status':
                $this->assertOptionLength($value);

                return array('status' => $value === '' ? null : array('name' => $value));

            case 'multi_select':
                return array('multi_select' => $this->buildMultiSelectOptions($value));

            case 'date':
                return array('date' => $value === '' ? null : array('start' => $value));

            case 'url':
                return array('url' => $value === '' ? null : $value);

            case 'relation':
                // Aquí $value ya es el page_id resuelto por findPageIdByExactTitle().
                return array('relation' => $value === '' ? array() : array(array('id' => $value)));
        }

        throw new SyncException(t('Unsupported property type: %s', $type));
    }

    /**
     * Un multi_select admite varios valores; la plantilla es un único texto, así
     * que se interpretan los valores separados por coma. Notion no admite comas
     * dentro del nombre de una opción, de modo que el separador nunca choca con
     * un valor legítimo.
     *
     * @param  string $value
     * @return array
     * @throws SyncException
     */
    private function buildMultiSelectOptions($value)
    {
        $options = array();

        foreach (explode(',', $value) as $option) {
            $option = trim($option);

            if ($option !== '') {
                $this->assertOptionLength($option);
                $options[] = array('name' => $option);
            }
        }

        if (count($options) > self::MAX_ARRAY_ITEMS) {
            throw new SyncException(t('A multi-select property accepts at most %d options per request.', self::MAX_ARRAY_ITEMS));
        }

        return $options;
    }

    /**
     * Notion rechaza cualquier contenido de texto de más de 2000 caracteres. El
     * plugin no trunca: avisa y deja la tarea pendiente para que el usuario
     * decida qué recortar.
     *
     * @param  string $value
     * @throws SyncException
     */
    private function assertTextLength($value)
    {
        if (mb_strlen($value) > self::MAX_TEXT_LENGTH) {
            throw new SyncException(t(
                'Notion limits text to %d characters and the resolved value has %d. Shorten the template or the task title.',
                self::MAX_TEXT_LENGTH,
                mb_strlen($value)
            ));
        }
    }

    /**
     * @param  string $value
     * @throws SyncException
     */
    private function assertOptionLength($value)
    {
        if (mb_strlen($value) > self::MAX_OPTION_LENGTH) {
            throw new SyncException(t(
                'Notion limits option names to %d characters and the resolved value has %d.',
                self::MAX_OPTION_LENGTH,
                mb_strlen($value)
            ));
        }
    }

    /**
     * Cabeceras comunes a todas las peticiones.
     *
     * @return array
     * @throws SyncException
     */
    private function getHeaders()
    {
        $settings = $this->notionSettingsModel->get();

        if (empty($settings['api_token'])) {
            throw new SyncException(t('The Notion integration token is missing from the plugin settings.'));
        }

        return array(
            'Authorization: Bearer '.$settings['api_token'],
            'Notion-Version: '.self::API_VERSION,
            'Content-Type: application/json',
        );
    }
}

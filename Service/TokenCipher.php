<?php

namespace Kanboard\Plugin\NotionSync\Service;

use Kanboard\Core\Base;
use Kanboard\Plugin\NotionSync\Exception\SyncException;

/**
 * Cifrado del token de integración de Notion antes de guardarlo en la base de
 * datos.
 *
 * Qué protege esto y qué no: la clave vive en el servidor de aplicación, así que
 * no defiende frente a alguien que ya controla ese servidor. Sí evita que el
 * token quede legible en un volcado de la base, en una réplica, en un backup de
 * MySQL o ante un acceso de solo lectura a la base (por ejemplo, una inyección
 * SQL), que es donde un secreto en claro suele acabar filtrándose.
 *
 * Se usa AES-256-GCM, que además de cifrar autentica: si el dato almacenado se
 * altera, el descifrado falla en lugar de devolver basura silenciosamente.
 */
class TokenCipher extends Base
{
    const CIPHER = 'aes-256-gcm';
    const PREFIX = 'enc:v1:';
    const IV_LENGTH = 12;
    const TAG_LENGTH = 16;
    const KEY_DIRECTORY = 'notionsync';
    const KEY_FILENAME = 'notionsync.key';

    /**
     * @var string
     */
    private $key = '';

    /**
     * Cifra un valor. Un valor vacío se guarda vacío, sin cifrar.
     *
     * @param  string $plaintext
     * @return string
     * @throws SyncException
     */
    public function encrypt($plaintext)
    {
        if ($plaintext === '' || $plaintext === null) {
            return '';
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->getKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new SyncException(t('The Notion token could not be encrypted.'));
        }

        return self::PREFIX.base64_encode($iv.$tag.$ciphertext);
    }

    /**
     * Descifra un valor almacenado.
     *
     * Un valor sin el prefijo se devuelve tal cual: así sigue funcionando un
     * token guardado antes de activarse el cifrado, que quedará cifrado la
     * próxima vez que se guarde la configuración.
     *
     * @param  string $stored
     * @return string
     */
    public function decrypt($stored)
    {
        if ($stored === '' || $stored === null) {
            return '';
        }

        if (! $this->isEncrypted($stored)) {
            return $stored;
        }

        $payload = base64_decode(substr($stored, strlen(self::PREFIX)), true);

        if ($payload === false || strlen($payload) <= self::IV_LENGTH + self::TAG_LENGTH) {
            $this->logger->error('NotionSync: el token almacenado está corrupto.');

            return '';
        }

        $plaintext = openssl_decrypt(
            substr($payload, self::IV_LENGTH + self::TAG_LENGTH),
            self::CIPHER,
            $this->getKey(),
            OPENSSL_RAW_DATA,
            substr($payload, 0, self::IV_LENGTH),
            substr($payload, self::IV_LENGTH, self::TAG_LENGTH)
        );

        if ($plaintext === false) {
            // Ocurre si se perdió o cambió la clave: el token es irrecuperable y
            // hay que volver a introducirlo desde la pantalla de configuración.
            $this->logger->error('NotionSync: no se pudo descifrar el token (¿cambió la clave de cifrado?).');

            return '';
        }

        return $plaintext;
    }

    /**
     * @param  string $value
     * @return boolean
     */
    public function isEncrypted($value)
    {
        return is_string($value) && strpos($value, self::PREFIX) === 0;
    }

    /**
     * Clave de 32 bytes.
     *
     * Se toma de NOTIONSYNC_ENCRYPTION_KEY (constante de config.php o variable
     * de entorno) si está definida, que es lo recomendable porque mantiene la
     * clave fuera del disco de datos. Si no lo está, se genera una y se guarda
     * bajo el directorio de archivos con permisos restrictivos, para que el
     * cifrado funcione sin configuración previa.
     *
     * @return string
     * @throws SyncException
     */
    private function getKey()
    {
        if ($this->key !== '') {
            return $this->key;
        }

        $configured = $this->getConfiguredKey();

        if ($configured !== '') {
            // Deriva 32 bytes de una clave de longitud arbitraria.
            $this->key = hash('sha256', $configured, true);

            return $this->key;
        }

        $this->key = $this->readOrCreateKeyFile();

        return $this->key;
    }

    /**
     * @return string
     */
    private function getConfiguredKey()
    {
        // Cualificada con la barra inicial: es una constante global definida en
        // config.php, no del namespace del plugin.
        if (defined('NOTIONSYNC_ENCRYPTION_KEY') && \NOTIONSYNC_ENCRYPTION_KEY !== '') {
            return \NOTIONSYNC_ENCRYPTION_KEY;
        }

        $fromEnv = getenv('NOTIONSYNC_ENCRYPTION_KEY');

        return $fromEnv === false ? '' : $fromEnv;
    }

    /**
     * @return string
     * @throws SyncException
     */
    private function readOrCreateKeyFile()
    {
        $path = $this->getKeyFilePath();

        if (file_exists($path)) {
            $contents = trim((string) file_get_contents($path));
            $key = @hex2bin($contents);

            if ($key !== false && strlen($key) === 32) {
                return $key;
            }

            throw new SyncException(t('The NotionSync encryption key file is invalid: %s', $path));
        }

        $this->createKeyDirectory(dirname($path));

        $key = random_bytes(32);

        if (@file_put_contents($path, bin2hex($key), LOCK_EX) === false) {
            throw new SyncException(t('Unable to write the NotionSync encryption key to %s', $path));
        }

        @chmod($path, 0600);

        return $key;
    }

    /**
     * Ruta del archivo de clave.
     *
     * Vive bajo FILES_DIR, en un subdirectorio propio del plugin. Se usa la
     * constante y no una ruta fija porque una instalación puede reubicar ese
     * directorio. Todo el árbol de datos queda fuera del alcance del servidor
     * web gracias al .htaccess que Kanboard trae en data/.
     *
     * @return string
     */
    private function getKeyFilePath()
    {
        return implode(DIRECTORY_SEPARATOR, array(FILES_DIR, self::KEY_DIRECTORY, self::KEY_FILENAME));
    }

    /**
     * @param  string $directory
     * @throws SyncException
     */
    private function createKeyDirectory($directory)
    {
        if (is_dir($directory)) {
            return;
        }

        if (! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new SyncException(t('Unable to create the NotionSync data directory: %s', $directory));
        }
    }
}

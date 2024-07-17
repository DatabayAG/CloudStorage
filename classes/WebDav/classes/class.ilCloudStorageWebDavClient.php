<?php

declare(strict_types=1);

use GuzzleHttp\Exception\GuzzleException;

/**
 * Class ilCloudStorageWebDavClient
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageWebDavClient
{

    const AUTH_BEARER = 'auth_bearer';

    protected ?ilCloudStorageWebDavDAVClient $sabre_client = null;
    
    protected ?ilCloudStorageWebDavRESTClient $rest_client = null;
    
    protected ?ilCloudStorageWebDav $dav = null;

    public static $unique_path_ids = array();
   
    const DEBUG = true;

    public function __construct(ilCloudStorageWebDav $a_dav)
    {
        $this->dav = $a_dav;
       
    }

    protected function getWebDAVClient(): ilCloudStorageWebDavDAVClient
    {
        if (!$this->sabre_client) {
            $this->sabre_client = new ilCloudStorageWebDavDAVClient($this->dav->getClientSettings());
        }
        return $this->sabre_client;
    }

    protected function getRESTClient(): ilCloudStorageWebDavRESTClient
    {
        if (!$this->rest_client) {
            $this->rest_client = new ilCloudStorageWebDavRESTClient($this->dav->config);
        }

        return $this->rest_client;
    }
    
    public function hasConnection(): bool
    {
        try {   //sabredav version 1.8 throws exception on missing connection
            $response = $this->getWebDAVClient()->request('GET', '', null, $this->dav->getHeaders());
        } catch (Exception $e) {
            return false;
        }

        return ($response['statusCode'] < 400);
    }

    public function getHTTPStatus(): int
    {
        global $DIC;
        try {
            $response = $this->getWebDAVClient()->request('PROPFIND', $this->dav->object->getRootFolder(), null, $this->dav->getHeaders());
        } catch (Exception $e) {
            $DIC->logger()->root()->error($e->getMessage());
            throw new ilCloudStorageException(ilCloudStorageException::NO_CONNECTION, $e->getMessage());
            return -1;
        }
        return $response['statusCode'];
    }


    /**
     * @param $id
     *
     * @return ilCloudStorageWebDavFile[]|ilCloudStorageWebDavFolder[]
     */
    public function listFolder($id)
    {
        global $DIC;
        $DIC->logger()->root()->log("listFolder");
        $id = $this->urlencode(ltrim($id, '/'));
        //$ilLog->write('listFolder: ' . $id);

        $settings = $this->dav->getClientSettings();
        if ($client = $this->getWebDAVClient()) {
            //$ilLog->write('listFolder: ' . $settings['baseUri'] . $id);

            $response = $client->propFind(
                $settings['baseUri'] . $id,
                [
                    '{DAV:}resourcetype',
                    '{DAV:}getcontentlength',
                    '{DAV:}getlastmodified'
                ],
                1,
                $this->dav->getHeaders()
            );
            //$DIC->logger()->root()->log(var_export($response,true));
            // $response = $client->propFind($settings['baseUri'] . $id, [], 1, $this->getAuth()->getHeaders());
            $items = ilCloudStorageWebDavItemFactory::getInstancesFromResponse($response, $this->dav->object->getRefId());
            $DIC->logger()->root()->log(var_export($items,true));
            return $items;
        }

        return array();
    }


    /**
     * @param $path
     *
     * @return bool
     */
    public function folderExists($path)
    {
        return $this->itemExists($path);
    }


    /**
     * @param $path
     *
     * @return bool
     */
    public function fileExists($path)
    {
        return $this->itemExists($path);
    }

    public function deliverFile(string $path): void
    {
        $path = ltrim($path, "/");
        $encoded_path = $this->urlencode($path);
        $headers = $this->dav->getHeaders();
        $settings = $this->dav->getClientSettings();
        $arr = $this->getWebDAVClient()->propFind($settings['baseUri'] . $encoded_path, array(), 1, $headers);
        $prop = array_shift($arr);
        //header("Content-type: " . $prop['{DAV:}getcontenttype']);
        header("Content-Length: " . $prop['{DAV:}getcontentlength']);
        header("Connection: close");
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        set_time_limit(0);
        $opts = array(
            'http' => array(
                'protocol_version' => 1.1,
                'method' => "GET",
                'header' => "Authorization: " . $headers['Authorization']
            )
        );
        $context = stream_context_create($opts);
        $file = fopen($settings['baseUri'] . $encoded_path, "rb", false, $context);
        fpassthru($file);
        exit;
    }


    /**
     * @param $path
     *
     * @return bool
     */
    public function createFolder($path): bool
    {
        $path = $this->urlencode($path);
        $response = $this->getWebDAVClient()->request('MKCOL', ltrim($path, '/'), null, $this->dav->getHeaders());
        if (self::DEBUG) {
            global $log;
            $log->write("[davClient]->createFolder({$path}) | response status Code: {$response['statusCode']}");
        }

        return ($response['statusCode'] == 200);
    }


    /**
     * urlencode without encoding slashes
     *
     * @param $str
     *
     * @return mixed
     */
    protected function urlencode($str)
    {
        return str_replace('%2F', '/', rawurlencode($str));
    }


    /**
     * @param $location
     * @param $local_file_path
     *
     * @return bool
     * @throws ilCloudException
     */
    public function uploadFile($location, $local_file_path)
    {
        $location = $this->urlencode(ltrim($location, '/'));
        if ($this->fileExists($location)) {
            $basename = pathinfo($location, PATHINFO_FILENAME);
            $extension = pathinfo($location, PATHINFO_EXTENSION);
            $i = 1;
            while ($this->fileExists($basename . "({$i})." . $extension)) {
                $i++;
            }
            $location = $basename . "({$i})." . $extension;
        }
        $response = $this->getWebDAVClient()->request('PUT', $location, file_get_contents($local_file_path), $this->dav->getHeaders());
        if (self::DEBUG) {
            global $log;
            $log->write("[davClient]->uploadFile({$location}, {$local_file_path}) | response status Code: {$response['statusCode']}");
        }

        return ($response['statusCode'] == 200);
    }


    /**
     * @param $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $response = $this->getWebDAVClient()->request('DELETE', ltrim($this->urlencode($path), '/'), null, $this->dav->getHeaders());
        if (self::DEBUG) {
            global $log;
            $log->write("[davClient]->delete({$path}) | response status Code: {$response['statusCode']}");
        }

        return ($response['statusCode'] == 200);
    }


    /**
     * @param $path
     *
     * @return bool
     */
    protected function itemExists($path)
    {
        try {
            $request = $this->getWebDAVClient()->request('GET', ltrim($this->urlencode($path), '/'), null, $this->dav->getHeaders());
        } catch (Exception $e) {
            return false;
        }

        return ($request['statusCode'] < 400);
    }


    /**
     * (re)initialize the client with settings from the davoud object
     */
    public function loadClient()
    {
        $this->sabre_client = new ilCloudStorageWebDavDAVClient($this->dav->getClientSettings());
    }


    /**
     * @param string    $path
     * @param ilObjUser $user
     *
     * @throws ilCloudPluginConfigException
     * @throws GuzzleException
     */
    public function shareItem($path, $user)
    {
        if ($user->getId() == $this->dav->object->getOwnerId()) {
            // no need to share with yourself (can result in an error with nextcloud)
            return;
        }
        $user_string = $this->dav->config->getMappingValueForUser($user);
        $shareAPI = $this->getRESTClient()->shareAPI($this->dav);
        $existing = $shareAPI->getForPath($path);
        foreach ($existing as $share) {
            if ($share->getShareWith() === $user_string) {
                if (!$share->hasPermission(ilCloudStorageWebDavShareAPI::PERM_TYPE_UPDATE)) {
                    $shareAPI->update($share->getId(), $share->getPermissions() | (ilCloudStorageWebDavShareAPI::PERM_TYPE_UPDATE + ilCloudStorageWebDavShareAPI::PERM_TYPE_READ));
                }
                return;
            }
        }
        $shareAPI->create($path, $user_string, ilCloudStorageWebDavShareAPI::PERM_TYPE_UPDATE + ilCloudStorageWebDavShareAPI::PERM_TYPE_READ);
    }


    /**
     * @param string $path
     *
     * @return int
     */
    public function pathToId(string $path) : int
    {
        $settings = $this->dav->getClientSettings();

        $client = $this->getWebDAVClient();

        $response = $client->propFind(
            $settings['baseUri'] . $this->urlencode($path),
            [
                '{http://davoud.org/ns}fileid'
            ],
            0,
            $this->dav->getHeaders()
        );

        $id = (int) (current($response));

        return $id;
    }

    public static function storeUniqueIdCache(array $uniqueId, int $refId): void {
        $_SESSION[(string)$refId."_uniqueid_cache"] = $uniqueId;
    }

    public static function getUniqueIdCache(int $refId): array {
        if (!isset($_SESSION[(string)$refId . "_uniqueid_cache"])) {
            $_SESSION[(string)$refId."_uniqueid_cache"] = array();
        }
        return $_SESSION[(string)$refId."_uniqueid_cache"];
    }

    public static function getUniqueId($cache) {
        return count($cache) + 1;
    }
}

<?php

declare(strict_types=1);

use League\Flysystem\Filesystem;
use League\Flysystem\WebDAV\WebDAVAdapter;
use Leapt\FlysystemOneDrive\OneDriveAdapter;
use Microsoft\Graph\Graph;
use Sabre\DAV\Client as SabreClient;
use ILIAS\DI\Container;
use League\Flysystem\StorageAttributes;

abstract class ilCloudStorageGenericFilesystem implements ilCloudStorageGenericService
{
    // POC: adapter type constants
    public const ADAPTER_WEBDAV   = 'webdav';
    public const ADAPTER_ONEDRIVE = 'onedrive';

    protected Container $dic;
    protected ilObjCloudStorage $object;
    protected ilCloudStorageConfig $config;

    protected Filesystem $filesystem;
    protected ?SabreClient $davClient = null;

    protected ?ilCloudStorageOAuth2 $userToken = null;
    protected ?ilCloudStorageBasicAuth $userAccount = null;

    public function __construct(int $refId, int $connId, string $adapter = self::ADAPTER_WEBDAV)
    {
        global $DIC;

        $this->dic    = $DIC;
        $this->config = ilCloudStorageConfig::getInstance($connId);
        $this->object = new ilObjCloudStorage($refId);

        $this->initializeFilesystem($adapter);
    }

    abstract public function getServiceId(): string;

    /*
     |--------------------------------------------------------------------------
     | Filesystem Initialization
     |--------------------------------------------------------------------------
     */

    protected function initializeFilesystem(string $adapter): void
    {
        $this->filesystem = match ($adapter) {
            self::ADAPTER_ONEDRIVE => $this->buildOneDriveFilesystem(),
            default                => $this->buildWebDAVFilesystem(),
        };
    }

    protected function buildWebDAVFilesystem(): Filesystem
    {
        $this->davClient = $this->createSabreClient();
        $adapter = new WebDAVAdapter($this->davClient);
        return new Filesystem($adapter);
    }

    protected function buildOneDriveFilesystem(): Filesystem
    {
        $graph = new Graph();
        $graph->setAccessToken($this->getToken()->getAccessToken());

        // 'root' can be overridden via config later
        $adapter = new OneDriveAdapter($graph, 'root');
        return new Filesystem($adapter);
    }

    protected function createSabreClient(): SabreClient
    {
        $settings = $this->getClientSettings();
        $headers  = [];

        if ($this->config->getAuthMethod() === ilCloudStorageConfig::AUTH_METHOD_BASIC) {
            $account = $this->getAccount();
            $headers['Authorization'] =
                'Basic ' . base64_encode(
                    $account->getUsername() . ':' .
                    ilCloudStorageUtil::decrypt($account->getPassword())
                );
        } elseif ($this->config->getAuthMethod() === ilCloudStorageConfig::AUTH_METHOD_OAUTH2) {
            $headers['Authorization'] = 'Bearer ' . $this->getToken()->getAccessToken();
        }

        return new SabreClient([
            'baseUri' => $settings['baseUri'],
            'headers' => $headers,
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | Auth Accessors
     |--------------------------------------------------------------------------
     */

    protected function getToken(): ilCloudStorageOAuth2
    {
        if (!$this->userToken) {
            $this->userToken = ilCloudStorageOAuth2::getUserToken(
                $this->object->getConnId(),
                $this->object->getOwnerId()
            );
        }
        return $this->userToken;
    }

    protected function getAccount(): ilCloudStorageBasicAuth
    {
        if (!$this->userAccount) {
            $this->userAccount = ilCloudStorageBasicAuth::getUserAccount(
                $this->object->getConnId(),
                $this->object->getOwnerId()
            );
        }
        return $this->userAccount;
    }

    /*
     |--------------------------------------------------------------------------
     | Core Filesystem Operations
     |--------------------------------------------------------------------------
     */

    public function listFolder(string $path = ''): array
    {
        $listing = $this->filesystem->listContents($path, false);
        $items   = [];

        foreach ($listing as $item) {
            if ($item->isDir()) {
                $folder = new ilCloudStorageFolder();
                $folder->setPath($item->path());
                $items[] = $folder;
            } else {
                assert($item instanceof StorageAttributes);
                $file = new ilCloudStorageFile();
                $file->setPath($item->path());
                $file->setSize($item[StorageAttributes::ATTRIBUTE_FILE_SIZE] ?? null);
                $items[] = $file;
            }
        }

        return $items;
    }

    public function createFolder(string $path): void
    {
        if (!$this->filesystem->directoryExists($path)) {
            $this->filesystem->createDirectory($path);
        }
    }

    public function uploadFile(string $destination, string $localPath): bool
    {
        $stream = fopen($localPath, 'r');
        $this->filesystem->writeStream($destination, $stream);
        fclose($stream);
        return true;
    }

    public function delete(string $path): bool
    {
        if ($this->filesystem->fileExists($path)) {
            $this->filesystem->delete($path);
        }
        if ($this->filesystem->directoryExists($path)) {
            $this->filesystem->deleteDirectory($path);
        }
        return true;
    }

    public function fileExists(string $path): bool
    {
        return $this->filesystem->fileExists($path);
    }

    public function folderExists(string $path): bool
    {
        return $this->filesystem->directoryExists($path);
    }

    public function deliverFile(string $path): void
    {
        $stream = $this->filesystem->readStream($path);
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Type: application/octet-stream');
        fpassthru($stream);
        fclose($stream);
        exit;
    }

    /*
     |--------------------------------------------------------------------------
     | Client Settings
     |--------------------------------------------------------------------------
     */

    protected function getClientSettings(): array
    {
        $this->dic->logger()->root()->debug("getClientSettings");

        return match ($this->config->getAuthMethod()) {
            $this->config::AUTH_METHOD_OAUTH2 => ilCloudStorageOAuth2::getClientSettings($this->config),
            $this->config::AUTH_METHOD_BASIC  => ilCloudStorageBasicAuth::getClientSettings($this->config),
            default                           => [],
        };
    }

    /*
     |--------------------------------------------------------------------------
     | Connection Check
     |--------------------------------------------------------------------------
     */

    public function hasConnection(): bool
    {
        try {
            $this->filesystem->listContents('', false);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
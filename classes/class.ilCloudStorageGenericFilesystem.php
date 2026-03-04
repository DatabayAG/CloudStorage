<?php

declare(strict_types=1);

use League\Flysystem\Filesystem;
use League\Flysystem\FileAttributes;
use League\Flysystem\WebDAV\WebDAVAdapter;
use Leapt\FlysystemOneDrive\OneDriveAdapter;
use Microsoft\Graph\Graph;
use Sabre\DAV\Client as SabreClient;
use ILIAS\DI\Container;

abstract class ilCloudStorageGenericFilesystem implements ilCloudStorageGenericService
{
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
        return new Filesystem(new WebDAVAdapter($this->davClient));
    }

    protected function buildOneDriveFilesystem(): Filesystem
    {
        $graph = new Graph();
        $graph->setAccessToken($this->getToken()->getAccessToken());
        return new Filesystem(new OneDriveAdapter($graph, 'root'));
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
        $listing = $this->filesystem->listContents(ltrim($path, '/'), false);
        $items   = [];

        foreach ($listing as $item) {
            if ($item->isDir()) {
                $folder = new ilCloudStorageFolder();
                $folder->setPath('/' . ltrim($item->path(), '/'));
                $items[] = $folder;
            } else {
                $file = new ilCloudStorageFile();
                $file->setPath('/' . ltrim($item->path(), '/'));
                if ($item instanceof FileAttributes) {
                    $file->setSize($item->fileSize());
                    if ($item->lastModified()) {
                        $file->setDateTimeLastModified(
                            date('D, d M Y H:i:s \G\M\T', $item->lastModified())
                        );
                    }
                }
                $items[] = $file;
            }
        }

        return $items;
    }

    public function addToFileTree(ilCloudStorageFileTree $file_tree, string $parent_folder = '/'): void
    {
        $this->dic->logger()->root()->debug('addToFileTree: ' . $parent_folder);
        $items = $this->listFolder($parent_folder);

        foreach ($items as $item) {
            $is_dir = $item instanceof ilCloudStorageFolder;
            $size   = $is_dir ? null : $item->getSize();
            $mtime  = $item->getDateTimeLastModified()
                ? strtotime($item->getDateTimeLastModified())
                : 0;
            $file_tree->addNode($item->getFullPath(), (int) $item->getId(), $is_dir, $mtime, $size);
        }
    }

    public function createFolder(string $path = '', ?ilCloudStorageFileTree $file_tree = null): void
    {
        if ($file_tree instanceof ilCloudStorageFileTree) {
            $path = ilCloudStorageUtil::joinPaths($file_tree->getRootPath(), $path);
        }
        $path = ltrim($path, '/');
        if ($path !== '' && !$this->filesystem->directoryExists($path)) {
            $this->filesystem->createDirectory($path);
        }
    }

    public function createFolderById(int $id, string $folder_name): int
    {
        $node = ilCloudStorageFileTree::getFileTreeFromSession($this->object->getRefId())
            ->getNodeFromId($id);
        $path = rtrim($node->getPath(), '/') . '/' . $folder_name;
        $this->createFolder($path);
        return ilCloudStorageFileNode::ID_UNKNOWN;
    }

    public function uploadFile(string $destination, string $localPath): bool
    {
        $stream = fopen($localPath, 'r');
        $this->filesystem->writeStream(ltrim($destination, '/'), $stream);
        fclose($stream);
        return true;
    }

    public function putFile(string $tmp_name, string $file_name, string $path = '', ?ilCloudStorageFileTree $file_tree = null): void
    {
        if ($file_tree instanceof ilCloudStorageFileTree) {
            $path = ilCloudStorageUtil::joinPaths($file_tree->getRootPath(), $path);
        }
        $destination = rtrim($path, '/') . '/' . $file_name;
        $this->uploadFile($destination, $tmp_name);
    }

    public function putFileById(string $tmp_name, string $file_name, int $id): bool
    {
        return false;
    }

    public function delete(string $path): bool
    {
        $path = ltrim($path, '/');
        if ($this->filesystem->fileExists($path)) {
            $this->filesystem->delete($path);
        } elseif ($this->filesystem->directoryExists($path)) {
            $this->filesystem->deleteDirectory($path);
        }
        return true;
    }

    public function deleteItem(string $path = '', ?ilCloudStorageFileTree $file_tree = null): void
    {
        if ($file_tree instanceof ilCloudStorageFileTree) {
            $path = ilCloudStorageUtil::joinPaths($file_tree->getRootPath(), $path);
        }
        $this->delete($path);
    }

    public function deleteItemById(int $id): bool
    {
        return false;
    }

    public function fileExists(string $path): bool
    {
        return $this->filesystem->fileExists(ltrim($path, '/'));
    }

    public function folderExists(string $path): bool
    {
        return $this->filesystem->directoryExists(ltrim($path, '/'));
    }

    public function deliverFile(string $path): void
    {
        $stream = $this->filesystem->readStream(ltrim($path, '/'));
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Type: application/octet-stream');
        fpassthru($stream);
        fclose($stream);
        exit;
    }

    public function getFile(string $path = '', ?ilCloudStorageFileTree $file_tree = null): void
    {
        $this->deliverFile($path);
    }

    public function getFileById(int $id): bool
    {
        return false;
    }

    /*
     |--------------------------------------------------------------------------
     | Connection Check
     |--------------------------------------------------------------------------
     */

    /**
     * @throws ilCloudStorageException
     */
    public function checkConnection(): void
    {
        try {
            $this->filesystem->listContents('', false)->toArray();
        } catch (Throwable $e) {
            throw new ilCloudStorageException(
                ilCloudStorageException::NO_CONNECTION,
                $e->getMessage()
            );
        }
    }

    public function hasConnection(): bool
    {
        try {
            $this->checkConnection();
            return true;
        } catch (ilCloudStorageException) {
            return false;
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Client Settings
     |--------------------------------------------------------------------------
     */

    protected function getClientSettings(): array
    {
        $this->dic->logger()->root()->debug('getClientSettings');

        return match ($this->config->getAuthMethod()) {
            $this->config::AUTH_METHOD_OAUTH2 => ilCloudStorageOAuth2::getClientSettings($this->config),
            $this->config::AUTH_METHOD_BASIC  => ilCloudStorageBasicAuth::getClientSettings($this->config),
            default                           => [],
        };
    }
}

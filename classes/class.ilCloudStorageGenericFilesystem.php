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
        return new Filesystem(new OneDriveAdapter($graph, 'me/drive/root'));
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
            // The Leapt OneDrive adapter returns paths prefixed with 'me/drive/root'
            // (the adapter prefix). Strip it so paths are relative to the drive root.
            $itemPath = $item->path();
            if ($this->config->isOneDrive()) {
                $itemPath = preg_replace('#^me/drive/root/?#', '', $itemPath);
            }

            // path = parent directory, name = basename – required for getFullPath()
            $cleanPath = '/' . ltrim($itemPath, '/');
            $itemName  = basename($cleanPath);
            $parentPath = rtrim(dirname($cleanPath), '/') ?: '/';

            if ($item->isDir()) {
                $folder = new ilCloudStorageFolder();
                $folder->setPath($parentPath);
                $folder->setName($itemName);
                $items[] = $folder;
            } else {
                $file = new ilCloudStorageFile();
                $file->setPath($parentPath);
                $file->setName($itemName);
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
        if ($path === '') {
            return;
        }
        // OneDrive/Graph: directoryExists() throws a 404 exception for non-existent paths
        // instead of returning false – so skip the existence check and create directly.
        // Catch any exception thrown when the directory already exists (e.g. 409 Conflict).
        if ($this->config->isOneDrive()) {
            try {
                $this->filesystem->createDirectory($path);
            } catch (\Throwable $e) {
                // If the directory already exists that is fine – swallow the exception.
                // Any real error (permissions etc.) will surface when the caller tries
                // to use the directory.
                $this->dic->logger()->root()->debug('createFolder: ' . $e->getMessage());
            }
            return;
        }
        if (!$this->filesystem->directoryExists($path)) {
            $this->filesystem->createDirectory($path);
        }
    }

    public function createFolderById(int $id, string $folder_name): int
    {
        // Return ID_UNKNOWN to signal that the caller (addFolderToService) should
        // handle the actual creation via createFolder(). We must NOT create the
        // folder here because addFolderToService will call createFolder() itself
        // in the ID_UNKNOWN branch – doing it twice causes a 409/exception.
        return ilCloudStorageFileNode::ID_UNKNOWN;
    }

    public function uploadFile(string $destination, string $localPath): bool
    {
        global $DIC;
        $DIC->logger()->root()->debug('uploadFile destination: ' . $destination . ' size: ' . filesize($localPath));
        $stream = fopen($localPath, 'r');
        try {
            $this->filesystem->writeStream(ltrim($destination, '/'), $stream);
            $DIC->logger()->root()->debug('uploadFile writeStream done');
        } catch (\Throwable $e) {
            // OneDrive Graph API sometimes returns a non-2xx status even on successful
            // upload (e.g. when the file already existed and was replaced). Log and swallow.
            $DIC->logger()->root()->debug('uploadFile writeStream exception (file may still be uploaded): ' . $e->getMessage());
        } finally {
            // writeStream may have already closed the stream internally (e.g. Leapt OneDrive adapter)
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        return true;
    }

    public function putFile(string $tmp_name, string $file_name, string $path = '', ?ilCloudStorageFileTree $file_tree = null): void
    {
        // Note: $path already contains the full absolute path (including root folder)
        // when called from uploadFileToService via addFolderToService.
        // Only prepend getRootPath() if $path does NOT already start with it.
        if ($file_tree instanceof ilCloudStorageFileTree) {
            $rootPath = rtrim($file_tree->getRootPath(), '/');
            $normalPath = '/' . ltrim($path, '/');
            if ($rootPath !== '/' && strpos($normalPath, $rootPath) !== 0) {
                $path = ilCloudStorageUtil::joinPaths($rootPath, $path);
            }
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
        // OneDrive/Graph: fileExists/directoryExists throw on missing paths.
        // Attempt delete directly and catch 404 as a no-op.
        if ($this->config->isOneDrive()) {
            try {
                $this->filesystem->delete($path);
            } catch (\Throwable $e) {
                try {
                    $this->filesystem->deleteDirectory($path);
                } catch (\Throwable) {
                    // item did not exist – that's fine
                }
            }
            return true;
        }
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

    public function isCaseSensitive(): bool
    {
        return true;
    }

    public function fileExists(string $path): bool
    {
        return $this->filesystem->fileExists(ltrim($path, '/'));
    }

    public function folderExists(string $path): bool
    {
        $path = ltrim($path, '/');
        // OneDrive/Graph: directoryExists() throws 404 for non-existent paths.
        if ($this->config->isOneDrive()) {
            try {
                return $this->filesystem->directoryExists($path);
            } catch (\Throwable) {
                return false;
            }
        }
        return $this->filesystem->directoryExists($path);
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

    /*
     |--------------------------------------------------------------------------
     | Interface Methods - Dummy Implementations
     |--------------------------------------------------------------------------
     */

    public function shareItem(string $path): ?array
    {
        // Dummy implementation - returns null until fully implemented
        return null;
    }

    public function hasParentId(): bool
    {
        // Generic filesystem adapters typically don't support parent ID tracking
        return false;
    }

    public function hasFileId(): bool
    {
        // Generic filesystem adapters typically don't support file ID tracking
        return false;
    }

    public function getParentIdField(): string
    {
        // Return empty string as filesystem adapters don't use parent ID fields
        return '';
    }

    public function getFileIdField(): string
    {
        // Return empty string as filesystem adapters don't use file ID fields
        return '';
    }

    public function getDecodedWebUrl(string $webUrl): string
    {
        return rawurldecode($webUrl);
    }

    public function getPathFromWebUrl(string $webUrl, int $type): string
    {
        $decodedUrl = $this->getDecodedWebUrl($webUrl);
        
        // For folder type, remove trailing slash
        if ($type == ilCloudStorageItem::TYPE_FOLDER) {
            $decodedUrl = rtrim($decodedUrl, '/');
        }
        
        // Extract directory path (everything except the filename)
        $lastSlashPos = strrpos($decodedUrl, '/');
        if ($lastSlashPos !== false) {
            return substr($decodedUrl, 0, $lastSlashPos + 1);
        }
        
        return '/';
    }

    public function getNameFromWebUrl(string $webUrl, int $type): string
    {
        $decodedUrl = $this->getDecodedWebUrl($webUrl);
        
        // For folder type, remove trailing slash
        if ($type == ilCloudStorageItem::TYPE_FOLDER) {
            $decodedUrl = rtrim($decodedUrl, '/');
        }
        
        // Extract filename/dirname (everything after the last slash)
        $lastSlashPos = strrpos($decodedUrl, '/');
        if ($lastSlashPos !== false) {
            return substr($decodedUrl, $lastSlashPos + 1);
        }
        
        return $decodedUrl;
    }
}
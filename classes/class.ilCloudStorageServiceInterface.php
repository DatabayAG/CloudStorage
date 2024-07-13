<?php

declare(strict_types=1);

// from class.ilCloudPluginService.php

interface ilCloudStorageServiceInterface
{

    public function getServiceName(): string;
    
    public static function getDefaultCollaborationAppFormats(): string;

    public static function getDefaultWebDavPath(): string;

    public static function getDefaultOAuth2Path(): string;
    
    public function authService(string $callback_url = ""): void;

    public function afterAuthService(): void;

    public function checkConnection(): void;

    public function checkAndRefreshAuthentication(): bool;

    public function getRootId(string $root_path): string;

    public function addToFileTree(ilCloudStorageFileTree $file_tree, string $parent_folder = "/"): void;
   
    public function addToFileTreeById(ilCloudStorageFileTree $file_tree, $id): bool;
    
    public function getFile(string $path = "", ?ilCloudStorageFileTree $file_tree = null): void;

    public function getFileById(int $id): bool;
    
    public function createFolder(string $path = "", ?ilCloudStorageFileTree $file_tree = null): void;

    public function createFolderById(int $parent_id, string $folder_name): int;
    
    public function putFile(string $tmp_name, string $file_name, string $path = '', ?ilCloudStorageFileTree $file_tree = null): void;

    public function putFileById(string $tmp_name, string $file_name, int $id): bool;

    public function deleteItem(string $path = "", ?ilCloudStorageFileTree $file_tree = null): void;

    public function deleteItemById(int $id): bool;

    public function isCaseSensitive(): bool;
    
}

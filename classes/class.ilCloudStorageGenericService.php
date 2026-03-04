<?php

interface ilCloudStorageGenericService
{
    public function listFolder(string $path): array;

    public function createFolder(string $path = '', ?ilCloudStorageFileTree $file_tree = null): void;

    public function createFolderById(int $id, string $folder_name): int;

    public function uploadFile(string $destination, string $localPath): bool;

    public function putFile(string $tmp_name, string $file_name, string $path = '', ?ilCloudStorageFileTree $file_tree = null): void;

    public function putFileById(string $tmp_name, string $file_name, int $id): bool;

    public function delete(string $path): bool;

    public function deleteItem(string $path = '', ?ilCloudStorageFileTree $file_tree = null): void;

    public function deleteItemById(int $id): bool;

    public function fileExists(string $path): bool;

    public function folderExists(string $path): bool;

    public function addToFileTree(ilCloudStorageFileTree $file_tree, string $parent_folder = '/'): void;

    public function getFile(string $path = '', ?ilCloudStorageFileTree $file_tree = null): void;

    public function getFileById(int $id): bool;

    /**
     * @throws ilCloudStorageException
     */
    public function checkConnection(): void;
}

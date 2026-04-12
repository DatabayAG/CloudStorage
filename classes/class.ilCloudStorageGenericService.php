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

    /**
     * Check if service has an active connection
     * Dummy implementation - returns false until fully implemented
     */
    public function hasConnection(): bool;

    /**
     * Share an item and get share URL/info
     * Dummy implementation - returns null until fully implemented
     */
    public function shareItem(string $path): ?array;

    /**
     * Check if service supports parent ID tracking
     */
    public function hasParentId(): bool;

    /**
     * Check if service supports file ID tracking
     */
    public function hasFileId(): bool;

    /**
     * Get the field name for parent ID in properties
     */
    public function getParentIdField(): string;

    /**
     * Get the field name for file ID in properties
     */
    public function getFileIdField(): string;

    /**
     * Get decoded web URL
     */
    public function getDecodedWebUrl(string $webUrl): string;

    /**
     * Extract path from web URL based on type
     */
    public function getPathFromWebUrl(string $webUrl, int $type): string;

    /**
     * Extract name from web URL based on type
     */
    public function getNameFromWebUrl(string $webUrl, int $type): string;

    public function isCaseSensitive(): bool;
}
<?php


interface ilCloudStorageGenericService
{
    public function listFolder(string $path): array;
    public function createFolder(string $path): void;
    public function uploadFile(string $destination, string $localPath): bool;
    public function delete(string $path): bool;
    public function fileExists(string $path): bool;
    public function folderExists(string $path): bool;
}

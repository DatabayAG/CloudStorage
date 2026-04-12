<?php

declare(strict_types=1);

/**
 * Class swdrTree
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */

class ilCloudStorageTree
{    
    public ?ilCloudStorageGenericService $service;

    function __construct(ilCloudStorageGenericService $service)
    {
        $this->service = $service;
    }

    public function getChilds($id, string $a_order = "", string $a_direction = "ASC"): array
    {
        $path = ilCloudStorageUtil::decodeBase64Path($id);
        try {
            return $this->service->listFolder($path);
        } catch (\Throwable $e) {
            // OneDrive returns 422 when listing children of a file.
            // Return empty array instead of crashing.
            return [];
        }
    }

    public function getRootNode(): ilCloudStorageFolder
    {
        $root = new ilCloudStorageFolder();
        $root->setName('');
        $root->setPath('/');
        return $root;
    }
}
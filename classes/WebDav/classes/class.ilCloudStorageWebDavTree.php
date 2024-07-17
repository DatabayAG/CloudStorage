<?php

declare(strict_types=1);

/**
 * Class swdrTree
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */

class ilCloudStorageWebDavTree
{    
    public $client;

    function __construct(ilCloudStorageWebDavClient $client)
    {
        $this->client = $client;
    }

    public function getChilds($id, string $a_order = "", string $a_direction = "ASC"): array
    {
        return $this->client->listFolder(ilCloudStorageUtil::decodeBase64Path($id));
    }

    function getRootNode()
    {
        $root = new ilCloudStorageWebDavFolder();
        $root->setName('');
        $root->setPath('/');

        return $root;
    }
}
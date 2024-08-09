<?php

declare(strict_types=1);

class ilCloudStorageWebDav extends ilCloudStorageGenericService
{
    public const SERVICE_ID = "dav";

    public const SERVICE_NAME = "WebDav";
    
    public function getServiceId(): string
    {
        return self::SERVICE_ID;
    }

    public function getServiceName(): string
    {
        return self::SERVICE_NAME;
    }

    public function hasCollaborationAppSupport(): bool
    {
        return false;
    }

    public function parentIdField(): string
    {
        return "";
    }

    public function fileIdField(): string
    {
        return "";
    }
}
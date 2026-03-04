<?php

declare(strict_types=1);

class ilCloudStorageOneDrive extends ilCloudStorageGenericFilesystem
{
    public const SERVICE_ID = 'odrv';

    public const SERVICE_NAME = 'OneDrive';

    public const ACCESS_TOKEN_EXPIRATION = '1 hour';

    public const REFRESH_TOKEN_EXPIRES = '6 month';

    public function __construct(int $refId, int $connId)
    {
        parent::__construct($refId, $connId, self::ADAPTER_ONEDRIVE);
    }

    public function getServiceId(): string
    {
        return self::SERVICE_ID;
    }

    public function getServiceName(): string
    {
        return self::SERVICE_NAME;
    }

    public static function getDefaultWebDavPath(): string
    {
        return '';
    }

    public static function getDefaultOAuth2Path(): string
    {
        return '';
    }

    public static function getDefaultCollaborationAppFormats(): string
    {
        return 'xls,xlsx,doc,docx,ppt,pptx,odt,ods,odp';
    }

    public function hasCollaborationAppSupport(): bool
    {
        return false;
    }

    public function getAccessTokenExpiration(): string
    {
        return self::ACCESS_TOKEN_EXPIRATION;
    }

    public function getRefreshTokenExpiration(): string
    {
        return self::REFRESH_TOKEN_EXPIRES;
    }
}

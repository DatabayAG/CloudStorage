<?php

declare(strict_types=1);

chdir('../../../../../../../');

require_once '../vendor/composer/vendor/autoload.php';
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/class.ilCloudStorageOAuth2.php');

$cookie_path = dirname($_SERVER['SCRIPT_NAME'], 8);
$cookie_path .= (!preg_match('/[\/\\\\]$/', $cookie_path)) ? '/' : '';
if ($cookie_path === '\\') {
    $cookie_path = '/';
}

define('IL_COOKIE_PATH', $cookie_path);

ilInitialisation::initILIAS();
ilCloudStorageOAuth2::redirect();

<?php

declare(strict_types=1);

$ilias_root_dir = __DIR__;
$i = 0;
while (!file_exists("$ilias_root_dir/ilias.ini.php") && $i <= 15) {
    $i++;
    $ilias_root_dir .= '/..';
}

if (!file_exists("$ilias_root_dir/ilias.ini.php")) {
    throw new RuntimeException('Could not find ilias.ini.php, are you sure this script is located within the ILIAS directory structure?');
}

chdir("$ilias_root_dir/public");

require_once '../vendor/composer/vendor/autoload.php';
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/class.ilCloudStorageOAuth2.php');

$cookie_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], $i));
if ($cookie_path === '' || $cookie_path === '.') {
    $cookie_path = '/';
} elseif (!str_ends_with($cookie_path, '/')) {
    $cookie_path .= '/';
}

define('IL_COOKIE_PATH', $cookie_path);

ilInitialisation::initILIAS();
ilCloudStorageOAuth2::redirect();

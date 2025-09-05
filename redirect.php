<?php

declare(strict_types=1);

chdir('../../../../../../../');

require_once '../vendor/composer/vendor/autoload.php';
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/class.ilCloudStorageOAuth2.php');

ilInitialisation::initILIAS();
ilCloudStorageOAuth2::redirect();

<?php

declare(strict_types=1);

chdir('../../../../../../../../../');

require_once('./Services/Init/classes/class.ilInitialisation.php');
ilInitialisation::initILIAS();
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/WebDav/classes/class.ilCloudStorageWebDav.php');
ilCloudStorageWebDav::redirectToObject();

?>
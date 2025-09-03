<?php

declare(strict_types=1);

echo "not implemented yet";

exit;

chdir('../../../../../../');

require_once('./components/ILIAS/Init/classes/class.ilInitialisation.php');
ilInitialisation::initILIAS();
chdir('./public');
require_once('./public/Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/class.ilCloudStorageOAuth2.php');
ilCloudStorageOAuth2::redirect();

?>
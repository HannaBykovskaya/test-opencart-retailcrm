<?php

require_once('config.php');
require_once(DIR_SYSTEM . 'startup.php');


$registry = new Registry();
$loader = new Loader($registry);
$loader->model('extension/module/retailcrm');
$model_retailcrm = $registry->get('model_extension_module_retailcrm');


$model_retailcrm->syncCustomersFromCRM();

$model_retailcrm->syncOrdersFromCRM();

echo "Synchronization complete.";
?>

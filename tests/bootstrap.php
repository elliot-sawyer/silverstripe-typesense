<?php
/**
 * Silverstripe Typesense module
 * @license LGPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense\Tests;

use SilverStripe\Core\Environment;
use SilverStripe\Core\EnvironmentLoader;

require BASE_PATH.'/vendor/autoload.php';
require BASE_PATH.'/vendor/silverstripe/framework/tests/bootstrap.php';

(new EnvironmentLoader())->loadFile(BASE_PATH.'/.env');

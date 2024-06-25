<?php
/**
 * Silverstripe Typesense module
 * @license GPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense\Tests;

use ElliotSawyer\SilverstripeTypesense\Typesense;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;

class TypesenseHelperTest extends SapphireTest
{
    public function testEnvironmentVariables()
    {
        $oldValue = Environment::getEnv('TYPESENSE_SERVER');
        Environment::setEnv('TYPESENSE_SERVER', null);
        $parts = Typesense::parse_typesense_server();
        $this->assertEmpty($parts);
        Environment::setEnv('TYPESENSE_SERVER', $oldValue);

        $parts = Typesense::parse_typesense_server();
        $this->assertArrayHasKey('scheme', $parts);
        $this->assertArrayHasKey('host', $parts);
        $this->assertArrayHasKey('port', $parts);
    }
}

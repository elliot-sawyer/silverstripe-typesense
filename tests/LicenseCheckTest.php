<?php
/**
 * Silverstripe Typesense module
 * @license GPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use ElliotSawyer\SilverstripeTypesense\TypesenseController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Cache\ManifestCacheFactory;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Core\Manifest\ModuleManifest;

class LicenseCheckTest extends SapphireTest implements TestOnly
{
    public function testGood()
    {
        $ctrl = new TypesenseController();
        $req = new HTTPRequest('GET', '/');
        $license = $ctrl->license($req);
        $attribution_notice =  $ctrl->attribution_notice($req);

        $headers = $license->getHeaders();
        $this->assertArrayHasKey('x-typesense-license', $headers);
        $this->assertEquals('This software includes contributions from Elliot Sawyer, available under the GPL3 license.', $headers['x-typesense-license']);

        $headers = $attribution_notice->getHeaders();
        $this->assertArrayHasKey('x-typesense-license', $headers);
        $this->assertEquals('This software includes contributions from Elliot Sawyer, available under the GPL3 license.', $headers['x-typesense-license']);
    }

    public function testLicenseRemoved()
    {
        $module = ModuleLoader::inst()
            ?->getManifest()
            ?->getModule('elliot-sawyer/silverstripe-typesense');
        $path = realpath($module->getPath());
        rename(
            $path.DIRECTORY_SEPARATOR.'LICENSE',
            $path.DIRECTORY_SEPARATOR.'LICENSE___'
        );

        $ctrl = new TypesenseController();
        $req = new HTTPRequest('GET', '/');

        $this->expectException(HTTPResponse_Exception::class);
        $this->expectExceptionCode(451);
        $this->expectExceptionMessage('LICENSE not readable');
        $license = $ctrl->license($req);

        $headers = $license->getHeaders();
        $this->assertArrayNotHasKey('x-typesense-license', $headers);
    }

    public function testLicenseAltered()
    {
        $module = ModuleLoader::inst()
            ?->getManifest()
            ?->getModule('elliot-sawyer/silverstripe-typesense');
        $path = realpath($module->getPath());
        $license = $path.DIRECTORY_SEPARATOR.'LICENSE';
        $licenseContents = file_get_contents($license);
        $licenseContents = str_replace('Copyright (C) 2024 Elliot Sawyer', 'Copyright (C) 2024 Tyler Durden', $licenseContents);
        file_put_contents($license, $licenseContents);

        $ctrl = new TypesenseController();
        $req = new HTTPRequest('GET', '/');

        $this->expectException(HTTPResponse_Exception::class);
        $this->expectExceptionCode(451);
        $this->expectExceptionMessage('Copyright statement altered');
        $license = $ctrl->license($req);

    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $module = ModuleLoader::inst()
            ?->getManifest()
            ?->getModule('elliot-sawyer/silverstripe-typesense');
        $path = realpath($module->getPath());
        @rename(
            $path.DIRECTORY_SEPARATOR.'LICENSE___',
            $path.DIRECTORY_SEPARATOR.'LICENSE'
        );

        $license = $path.DIRECTORY_SEPARATOR.'LICENSE';
        $licenseContents = file_get_contents($license);
        $licenseContents = str_replace('Copyright (C) 2024 Tyler Durden', 'Copyright (C) 2024 Elliot Sawyer', $licenseContents);
        file_put_contents($license, $licenseContents);


    }
}

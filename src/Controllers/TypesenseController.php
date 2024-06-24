<?php
/**
 * Silverstripe Typesense module
 * @license GPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense;

use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Manifest\Module;
use SilverStripe\Core\Manifest\ModuleLoader;

class TypesenseController extends Controller
{
    private static $allowed_actions = [
        'license',
        'attribution_notice',
    ];

    /**
     * Outputs licence information to comply with GPL3-Attribution
     * `final` keyword prevents overriding / replacement with Injector
     *
     * @param $request
     * @return HttpResponse
     */
    final public function license(HTTPRequest $request): HTTPResponse
    {
        $licenseContent = 'Unable to load license';
        $module = ModuleLoader::inst()
            ?->getManifest()
            ?->getModule('elliot-sawyer/silverstripe-typesense');
        if($module instanceof Module) {
            $path = realpath($module->getPath());
            $license = $path . DIRECTORY_SEPARATOR . 'LICENSE';
            if(is_readable($license)) {
                $licenseContent = file_get_contents($license);
                if(!preg_match('/Copyright \(C\) \d{4} Elliot Sawyer/', $licenseContent)) {
                    $licenseContent = "Copyright statement altered";
                    $this->httpError(451, $licenseContent);
                }
            } else {
                $licenseContent = "LICENSE not readable";
                $this->httpError(451, $licenseContent);
            }
        } else {
            $licenseContent = "Module named 'elliot-sawyer/silverstripe-typesense' not readable";
            $this->httpError(451, $licenseContent);
        }
        $this->attribution_notice($request);
        return $this->getResponse()->setBody($licenseContent);
    }

    /**
     * Outputs "Attribution Notice" to comply with Section 0 of
     * GPL3 with Attribution License
     *
     * @param HTTPRequest|null $request
     * @return string
     */
    final public function attribution_notice(HTTPRequest $request = null): HTTPResponse
    {
        $attribution_notice = "This software includes contributions from Elliot Sawyer, available under the GPL3 license.";
        if($request && $request instanceof HTTPRequest) {
            $this->getResponse()->addHeader('Content-Type', 'text/plain');
            $this->getResponse()->addHeader('X-Typesense-License', $attribution_notice);
        }
        return $this->getResponse()->setBody($attribution_notice);

    }
}

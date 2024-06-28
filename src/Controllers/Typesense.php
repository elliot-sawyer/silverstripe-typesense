<?php
/**
 * Silverstripe Typesense module
 * @license LGPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense;

use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Manifest\Module;
use SilverStripe\Core\Manifest\ModuleLoader;
use Typesense\Client;

final class Typesense extends Controller
{
    private static $allowed_actions = [
        'license',
        'attribution_notice',
    ];

    //overridden with YML
    private static $collections = [];

    private static $connection_timeout = 2;
    /**
     * Return a globally accessible Typesense client for this environment
     * By default it accounts for a server configured with a dev environment
     * If a host, port, and protocol are not configured locally, they are loaded via the database
     *
     * @param array $options this is only used if a developer needs to overload the default client for some reason
     * @return \Typesense\Client
     */
    public static function client($connection_timeout = 0)
    {
        if(!$connection_timeout) {
            $connection_timeout = self::config()->connection_timeout;
        }
        $server = self::parse_typesense_server();
        if(!$server) throw new Exception('TYPESENSE_SERVER must be in scheme://host:port format');

        $localhost = $server['host'] ?? 'localhost';
        $localport = $server['port'] ?? 8081;
        $localprotocol = $server['scheme'] ?? 'http';

        $nodes = [];
        if($localhost && $localport && $localprotocol) {
            $nodes[] = [
                'host' => $localhost,
                'port' => $localport,
                'protocol' => $localprotocol,
            ];
        }

        $client = new Client([
            'api_key' => Environment::getEnv('TYPESENSE_API_KEY'),
            'nodes' => $nodes,
            'connection_timeout' => (int) $connection_timeout
        ]);

        return $client;
    }

    /**
     * Parse Typesense server environment variable
     *
     * @return array
     */
    public static function parse_typesense_server() : array
    {
        $server = Environment::getEnv('TYPESENSE_SERVER') ?? '';
        $parts = parse_url($server);
        return count($parts) == 3 ? $parts : [];
    }

    /**
     * Outputs licence information to comply with GPL3-Attribution
     * `final` keyword prevents overriding / replacement with Injector
     *
     * @param $request
     * @return HttpResponse
     */
    public function license(HTTPRequest $request): HTTPResponse
    {
        $licenseContent = 'Unable to load license';
        $module = ModuleLoader::inst()
            ?->getManifest()
            ?->getModule('elliot-sawyer/silverstripe-typesense');
        if($module instanceof Module) {
            $path = realpath($module->getPath());
            $license = $path . DIRECTORY_SEPARATOR . 'LICENSE.md';
            if(is_readable($license)) {
                $licenseContent = file_get_contents($license);
                if(!preg_match('/Copyright \(C\) \d{4} Elliot Sawyer/', $licenseContent)) {
                    $licenseContent = "Copyright statement altered";
                    $this->httpError(451, $licenseContent);
                }
            } else {
                $licenseContent = "LICENSE.md not readable";
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
    public function attribution_notice(HTTPRequest $request = null): HTTPResponse
    {
        $attribution_notice = $this->CopyrightStatement();
        if($request && $request instanceof HTTPRequest) {
            $this->getResponse()->addHeader('Content-Type', 'text/plain');
            $this->getResponse()->addHeader('X-Typesense-License', $attribution_notice);
        }
        return $this->getResponse()->setBody($attribution_notice);

    }

    public function CopyrightStatement(): string
    {
        return "This software includes contributions from Elliot Sawyer, available under the LGPL v3.0 license.";
    }
}

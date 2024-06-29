<?php
/**
 * Silverstripe Typesense module
 * @license LGPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Security\PermissionProvider;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

class TypesenseAdmin extends ModelAdmin implements PermissionProvider
{
    private static $url_segment = 'typesense';
    private static $menu_title = 'Typesense';
    private static $managed_models = [
        'collections' => [
            'dataClass' => Collection::class,
            'title' => 'Collections'
        ]
    ];
    private static $menu_icon_class = 'font-icon-dashboard';
    private static $required_permission_codes = 'CMS_ACCESS_TYPESENSEADMIN';

    public function providePermissions()
    {
        $title = _t(TypesenseAdmin::class.'.MENUTITLE', LeftAndMain::menu_title('TypesenseAdmin'));

        return [
            'CMS_ACCESS_TYPESENSEADMIN' => [
                'name' => _t(CMSMain::class.'.ACCESS', "Access to '{title}' section", 'Permissions Label', ['title' => $title]),
                'category' => $title,
                'help' => 'Allow use of the Typesense Administration area',
            ],
        ];
    }

    public function getGridfieldConfig(): GridFieldConfig
    {
        $cfg = parent::getGridFieldConfig();
        if($this->modelClass == Collection::class) {
            $cfg->addComponent(GridFieldOrderableRows::create());
        }
        return $cfg;
    }
}

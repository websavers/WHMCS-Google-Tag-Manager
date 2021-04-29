<?php
/**
 * WHMCS Google Tag Manager Module
 *
 * An addon module allows you to add additional functionality to WHMCS. It
 * can provide both client and admin facing user interfaces, as well as
 * utilise hook functionality within WHMCS.
 *
 * This sample file demonstrates how an addon module for WHMCS should be
 * structured and exercises all supported functionality.
 *
 * Addon Modules are stored in the /modules/addons/ directory. The module
 * name you choose must be unique, and should be all lowercase, containing
 * only letters & numbers, always starting with a letter.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "addonmodule" and therefore all functions
 * begin "gtm_".
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/addon-modules/
 *
 * @copyright Copyright (c) Websavers Inc 2021
 * @license LICENSE file included in this package
 */

/**
 * Require any libraries needed for the module to function.
 * require_once __DIR__ . '/path/to/library/loader.php';
 *
 * Also, perform any initialization required by the service's library.
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define addon module configuration parameters.
 *
 * Includes a number of required system fields including name, description,
 * author, language and version.
 *
 * Also allows you to define any configuration parameters that should be
 * presented to the user when activating and configuring the module. These
 * values are then made available in all module function calls.
 *
 * Examples of each and their possible configuration parameters are provided in
 * the fields parameter below.
 *
 * @return array
 */
function gtm_config()
{
    return [
        // Display name for your module
        'name' => 'Google Tag Manager',
        // Description displayed within the admin interface
        'description' => 'Automatically includes the GTM embed codes and provides the necessary JavaScript dataLayer for eCommerce variables',
        // Module author name
        'author' => 'Websavers Inc.',
        // Default language
        'language' => 'english',
        // Version number
        'version' => '1.0',
        'fields' => [
            // a text field type allows for single line text input
            'gtm-container-id' => [
                'FriendlyName' => 'GTM Container ID',
                'Type' => 'text',
                'Size' => '15',
                'Default' => 'GTM-0123456',
                'Description' => 'Enter your GTM container ID here.',
            ],
        ]
    ];
}

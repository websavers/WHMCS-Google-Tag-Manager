<?php
/**
 * WHMCS Google Tag Manager Module Hooks File
 *
 * Hooks allow you to tie into events that occur within the WHMCS application.
 *
 * This allows you to execute your own code in addition to, or sometimes even
 * instead of that which WHMCS executes by default.
 *
 * @see https://developers.whmcs.com/hooks/
 *
 * @copyright Copyright (c) Websavers Inc 2021
 * @license LICENSE file included in this package
 */

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) die("This file cannot be accessed directly");
  
function gtm_get_module_settings($setting){
  
    if ($setting == null || empty($setting)){
      return Capsule::table('tbladdonmodules')->select('setting', 'value')
            ->where('module', 'google_tag_manager')
            ->get();
    }
    else{
      return Capsule::table('tbladdonmodules')->select('setting', 'value')
            ->where('module', 'google_tag_manager')
            ->where('setting', $setting)
            ->value('value');
    }
}

/** The following two hooks output the code required for GTM to function **/

add_hook('ClientAreaHeadOutput', 1, function($vars) {
    $container_id = gtm_get_module_settings('gtm-container-id');
    return "<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','$container_id');</script>
<!-- End Google Tag Manager -->";
});

add_hook('ClientAreaHeaderOutput', 1, function($vars) {
    $container_id = gtm_get_module_settings('gtm-container-id');
    return "<!-- Google Tag Manager (noscript) -->
  <noscript><iframe src='https://www.googletagmanager.com/ns.html?id=$container_id'
  height='0' width='0' style='display:none;visibility:hidden'></iframe></noscript>
  <!-- End Google Tag Manager (noscript) -->";
});

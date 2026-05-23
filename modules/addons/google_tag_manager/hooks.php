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

define("MODULENAME", 'google_tag_manager');
  
function gtm_get_module_settings($setting){
    
    if ($setting == null || empty($setting)){
      return Capsule::table('tbladdonmodules')->select('setting', 'value')
            ->where('module', MODULENAME)
            ->get();
    }
    else{
      return Capsule::table('tbladdonmodules')
            ->where('module', MODULENAME)
            ->where('setting', $setting)
            ->value('value');
    }
    
}

//Remove currency prefix and code, like $ and CAD. Swap comma for dot separator.
function gtm_format_price($price, $currencyCode, $prefix){ 
  return str_ireplace([$prefix, ',', ' ', $currencyCode],['','.','',''],$price); 
}

function gtm_ga_module_in_use(){
  $ga_site_tag = Capsule::table('tbladdonmodules')
        ->where('module', 'google_analytics')
        ->where('setting', 'code')
        ->value('value');
        
  $active_addons = Capsule::table('tblconfiguration')
        ->where('setting', 'ActiveAddonModules')
        ->value('value');
        
  $ga_is_active = (strpos($active_addons, 'google_analytics') !== false)? true:false;
        
  return ($ga_is_active && !empty($ga_site_tag))? true:false;
}

/**
 * Validate and sanitize the GTM container ID before injecting it into HTML/JS.
 *
 * Google Tag Manager container IDs follow the format `GTM-XXXXXXX` where the
 * suffix is alphanumeric (uppercase). Anything outside that pattern is either
 * a typo or an attempted injection, so we reject it. This stops a malicious
 * (or compromised) admin from using the settings field as an XSS vector that
 * would execute on every Client Area page load.
 *
 * Returns the validated ID, or an empty string if invalid.
 */
function gtm_safe_container_id($raw){
  if (empty($raw)) return '';
  $raw = trim($raw);
  return preg_match('/^GTM-[A-Z0-9]+$/', $raw) ? $raw : '';
}

/** The following hooks output the code required for GTM to function **/

/**
 * Optional: inject Google Consent Mode v2 default-denied bootstrap BEFORE the
 * GTM loader runs. When enabled, every Google tag GTM ships sees the correct
 * consent state from the very first dataLayer event. Without this, tags would
 * fire in their default (granted) mode until a cookie banner has a chance to
 * call gtag('consent', 'update', ...), which on a fast-loading page can mean
 * one or more page_views are recorded before consent is captured.
 *
 * Priority is 0 so this hook runs before the GTM loader (priority 1) registered
 * below. WHMCS executes hooks of the same event in ascending priority order.
 *
 * The cookie banner (or external CMP) is responsible for calling
 * gtag('consent', 'update', { ... }) on accept; this hook only sets the
 * default state.
 */
add_hook('ClientAreaHeadOutput', 0, function($vars) {

  if ( gtm_get_module_settings('gtm-enable-consent-mode') !== 'on' ) return '';
  $container_id = gtm_safe_container_id(gtm_get_module_settings('gtm-container-id'));
  if (empty($container_id)) return '';

  $script = "<!-- Google Consent Mode v2 default (RareCloud / WHMCS-GTM module) -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
window.gtag = gtag;
gtag('consent', 'default', {
  'ad_storage': 'denied',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
  'analytics_storage': 'denied',
  'functionality_storage': 'denied',
  'personalization_storage': 'denied',
  'security_storage': 'granted',
  'wait_for_update': 500
});
gtag('set', 'ads_data_redaction', true);";

  // url_passthrough decorates outbound URLs with _gl/gclid query parameters so
  // GA can stitch sessions across domains when analytics_storage is denied.
  // Enabling it site-wide breaks WHMCS account forms on installs with URL
  // rewriting (the extra params land in the action= path and the POST 404s).
  // It is only meaningfully useful during checkout (cart -> payment gateway),
  // so restrict it to cart.php and gate behind an opt-in setting. See upstream
  // issue #22.
  if (
    gtm_get_module_settings('gtm-url-passthrough-cart') === 'on'
    && basename($_SERVER['PHP_SELF'] ?? '') === 'cart.php'
  ) {
    $script .= "\ngtag('set', 'url_passthrough', true);";
  }

  $script .= "\n</script>\n<!-- End Google Consent Mode v2 default -->";
  return $script;

});

add_hook('ClientAreaHeadOutput', 1, function($vars) {

  $container_id = gtm_safe_container_id(gtm_get_module_settings('gtm-container-id'));

  if (!empty($container_id)):
    return "<!-- Google Tag Manager -->
<script>window.dataLayer = window.dataLayer || [];</script>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','$container_id');</script>
<!-- End Google Tag Manager -->";
  endif;

});

add_hook('ClientAreaHeaderOutput', 1, function($vars) {

  $container_id = gtm_safe_container_id(gtm_get_module_settings('gtm-container-id'));
  if (!empty($container_id)):
    return "<!-- Google Tag Manager (noscript) -->
<noscript><iframe src='https://www.googletagmanager.com/ns.html?id=$container_id'
height='0' width='0' style='display:none;visibility:hidden'></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->";
  endif;

});

/** JavaScript dataLayer Variables **/

add_hook('ClientAreaFooterOutput', 1, function($vars) {

  if ( gtm_get_module_settings('gtm-enable-datalayer') == 'off' ) return '';
  // https://classdocs.whmcs.com/7.6/WHMCS/Billing/Currency.html
  $currency = $vars['activeCurrency']; //obj
  $currencyCode = $currency->code;
  $currencyPrefix = $currency->prefix; 
  $lang = $vars['activeLocale']['languageCode'];

  $itemsArray = array();
  $js_events = '';

  switch($vars['templatefile']){

    case 'configureproduct':

      $productAdded = $vars['productinfo'];
      $selectedCycle = $vars['billingcycle'];
      if ($vars['pricing']['type'] == "onetime") {
        $price = (string)$vars['pricing']['minprice']['simple'];
      } else {
        $price = (string)$vars['pricing']['rawpricing'][$selectedCycle];
      }
  
      $itemsArray[] = array(
        'item_name'       => htmlspecialchars_decode($productAdded['name']),
        'item_id'         => $productAdded['pid'],
        'price'           => gtm_format_price($price, $currencyCode, $currencyPrefix), //uses rawpricing so prefix technically doesn't matter
        'item_category'   => $productAdded['group_name'],
        'quantity'        => 1,
        'currency'        => $currencyCode
      );
      $event = 'view_item';
      $action = 'configureproduct';

      break;

    case 'configuredomains':

      if (is_array($vars['domains'])){ //domain config
        foreach($vars['domains'] as $domain){
          if (is_array($domain)){
            $itemsArray[] = array(                        
              'name'      => ucfirst($domain['type']), //Register, Transfer, Renewal
              'price'     => gtm_format_price($domain['price'], $currencyCode, $currencyPrefix),
              'category'  => 'Domain',
              'quantity'  => 1,
              'currency'  => $currencyCode
            );
         }
        }
      }
      $event = 'view_item';
      $action = 'configuredomains';

      break;

    case 'viewcart':

      foreach($vars['products'] as $productAdded){
        //https://classdocs.whmcs.com/8.1/WHMCS/View/Formatter/Price.html
        $price = $productAdded['pricing']['baseprice'];
        if (is_object($price)) $price = $price->toNumeric();
        $itemsArray[] = array(                       
          'name'      => htmlspecialchars_decode($productAdded['productinfo']['name']),
          'id'        => $productAdded['productinfo']['pid'],
          'price'     => $price, //don't need formatter since we received it formatted
          'category'  => $productAdded['productinfo']['groupname'],
          'quantity'  => 1,
          'currency'  => $currencyCode
        );
	foreach ($productAdded['addons'] as $productAddon) {
          $addonPrice = $productAddon['pricingtext'];
          if (is_object($addonPrice)) $addonPrice= $addonPrice->toNumeric();
          $itemsArray[] = array(
            'name'      => htmlspecialchars_decode($productAddon['name']),
            'id'        => $productAddon['addonid'],
            'price'     => $addonPrice, //don't need formatter since we received it formatted
            'category'  => $productAdded['productinfo']['groupname'],
            'quantity'  => $productAddon['qty'],
            'currency'  => $currencyCode
          );
        }
      }
      if ($_REQUEST['a'] == 'view'){
        $event = 'add_to_cart';
        $action = 'viewcart';
      }
      else if ($_REQUEST['a'] == 'checkout'){
        $event = 'begin_checkout';
        $action = 'checkout';
      }

      $js_events .= '
      // Empty Cart Event
      var emptyCartButton = document.getElementById("btnEmptyCart");
      if (emptyCartButton != null) {
        document.getElementById("btnEmptyCart").onclick = function(){
          dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
          dataLayer.push({
            event: "remove_from_cart",
            ecommerce: { items: ' . json_encode($itemsArray) . ' }
          });
        };
      }';

      break;

  }

  if (!empty($itemsArray) && !empty($event)){
  
    $eventArray = array(
      'event'         => $event,
      'eventAction'   => $action,
      'ecommerce'     => array( 'items' => $itemsArray )
    );

    return "<script id='GTM_DataLayer'>
    dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
    dataLayer.push(" . json_encode($eventArray) . ");
    " . $js_events . "
</script>";

  }
  
});

/**
 * https://developers.whmcs.com/hooks-reference/shopping-cart/#shoppingcartcheckoutcompletepage
 */
add_hook('ShoppingCartCheckoutCompletePage', 1, function($vars) {

  if ( gtm_get_module_settings('gtm-enable-datalayer') == 'off' ) return '';
    
  $res_orders = localAPI('GetOrders', array('id' => $vars['orderid']));
  $order = $res_orders['orders']['order'][0];
  
  $currencyCode = $order['currencysuffix'];
  $currencyPrefix = $order['currencyprefix'];
	
  //if ( $_REQUEST['debug'] ) var_dump($order); ///DEBUG
  
  $itemsArray = array();
  foreach ($order['lineitems']['lineitem'] as $product){
    $p_g_n = explode(' - ', $product['product']);
    if ( count($p_g_n) == 1 ){ 
      $category = '';
      $name = $product['product'];
    }
    else if ( count($p_g_n) == 2 ){
      $category = $p_g_n[0];
      $name = $p_g_n[1];
    }
    $itemsArray[] = array(
      'item_name'      => $name,
      'item_id'        => $product['relid'],
      'price'          => gtm_format_price($product['amount'], $currencyCode, $currencyPrefix),
      'item_brand'     => '',
      'item_category'  => $category,
      'quantity'       => 1,
      'currency'       => $currencyCode
    );
  }
  
  $res_invoice = localAPI('GetInvoice', array('invoiceid' => $order['invoiceid']));
  $tax = (float)$res_invoice['tax'] + (float)$res_invoice['tax2'];
  
  $eventArray = array(
    'event' => 'purchase',
    'ecommerce' => array(
      'transaction_id'  => $order['id'],
      'affiliation'     => 'WHMCS Orderform',
      'value'           => $order['amount'], // Total transaction value (incl. tax and shipping)
      'tax'             => $tax,
      'shipping'        => '',
      'currency'        => $currencyCode,
      'coupon'          => $order['promocode'],
      'items'           => $itemsArray
    )
  );

  return "<script id='GTM_DataLayer'>
    dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
    dataLayer.push(" . json_encode($eventArray) . ");
  </script>";
  
});


add_hook('ClientAreaPageRegister', 1, function($vars) {

	if ( gtm_get_module_settings('gtm-enable-datalayer') == 'off' ) return '';

	add_hook('ClientAreaFooterOutput', 1, function($vars) {

		// We push a privacy-minimal sign_up event: just `event` + `method`.
		// We deliberately do NOT include first_name, last_name, email, phone or
		// address fields here. Google Analytics' Terms of Service explicitly
		// forbid sending personally identifiable information (PII) to GA, and
		// under GDPR the principle of data minimisation requires that we only
		// transfer what is strictly necessary for the analytics purpose. A
		// sign_up conversion only needs to know that a sign-up happened, not
		// who signed up. If you need user-level conversion attribution for
		// Google Ads, configure Google Ads Enhanced Conversions in the GTM UI;
		// that pathway hashes the PII (SHA-256) before sending and uses a
		// separate, audited transfer mechanism.
		//
		// We also use a "submit" listener instead of hijacking the submit
		// button click + e.preventDefault() + register_form.submit(). The old
		// approach broke client-side form validation and blocked any other
		// JavaScript that listened to the form's normal submit lifecycle.
		return <<<HTML
<script id="GTM_DataLayer">
(function() {
  var form = document.getElementById("frmCheckout");
  if (!form) return;

  // Preserve the previous client-side UX patch: WHMCS' default
  // clientregister.tpl does not mark email/password as `required`, so empty
  // submissions silently round-trip to the server before erroring. Add the
  // attribute client-side so the browser's native validation catches it
  // immediately. Unrelated to analytics, but kept here to avoid a UX
  // regression for sites that relied on this behaviour.
  document.querySelectorAll("#inputNewPassword1, #inputNewPassword2, #inputEmail").forEach(function(field) {
    field.setAttribute("required", "");
  });

  form.addEventListener("submit", function() {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: "sign_up",
      method: "WHMCS"
    });
  });
})();
</script>
HTML;
	});

});
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

/** The following two hooks output the code required for GTM to function **/

add_hook('ClientAreaHeadOutput', 1, function($vars) {
  
  $container_id = gtm_get_module_settings('gtm-container-id');

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

  $container_id = gtm_get_module_settings('gtm-container-id');
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
    
  $productAdded = $vars['productinfo'];
  $domainsAdded = $vars['domains'];
  $productsAdded = $vars['products']; 
  
  $currencyCode = $vars['activeCurrency']['code'];
  $lang = $vars['activeLocale']['languageCode'];

  $currencyPrefix = '$'; //default, get live currency prefix below
  foreach ( $vars['currencies'] as $currency ){
    if ($currency['code'] === $currencyCode){
      $currencyPrefix = $currency['prefix']; 
    }
  }
  
  //if ( $_REQUEST['debug'] ) var_dump($vars['activeCurrency']['code']); ///DEBUG
  
  $itemsArray = array();
  
  if (!empty($productAdded)){ //product config
    $selectedCycle = $vars['billingcycle'];
    $price = (string)$vars['pricing']['rawpricing'][$selectedCycle];

    $itemsArray[] = array(
      'item_name'      => $productAdded['name'],
      'item_id'        => $productAdded['pid'],
      'price'     => gtm_format_price($price, $currencyCode, $currencyPrefix), //uses rawpricing so prefix technically doesn't matter
      'item_category'  => $productAdded['group_name'],
      'quantity'  => 1
    );
  }
  if (!empty($domainsAdded) && is_array($domainsAdded)){ //domain config
    foreach($domainsAdded as $domain){
      if (is_array($domain)){
        $itemsArray[] = array(                        
          'name'      => ucfirst($domain['type']), //Register, Transfer, Renewal
          'price'     => gtm_format_price($domain['price'], $currencyCode, $currencyPrefix),
          'category'  => 'Domain',
          'quantity'  => 1
        );
     }
    }
  }
  if (!empty($productsAdded)){ //viewcart
    foreach($productsAdded as $productAdded){
      //$price = (string)$productAdded['pricing']['totaltoday'];
      $price = $productAdded['pricingtext'];
      $itemsArray[] = array(                       
        'name'      => $productAdded['productinfo']['name'],
        'id'        => $productAdded['productinfo']['pid'],
        'price'     => gtm_format_price($price, $currencyCode, $currencyPrefix),
        'category'  => $productAdded['productinfo']['groupname'],
        'quantity'  => 1
      );
    }
  }
    
  switch ($vars['templatefile']){
		
    /*
    case 'configureproductdomain':
    
      $event = 'domain_selection';
      $action = 'configureproductdomain';
      break;
    */

    case 'configureproduct':

      $event = 'view_item';
      $action = 'configureproduct';
      break;
      
    case 'configuredomains':
    
      $event = 'view_item';
      $action = 'configuredomains';
      break; 
      
    case 'viewcart':

      if ($_REQUEST['a'] == 'view'){
        $event = 'add_to_cart';
        $action = 'viewcart';
      }
      else if ($_REQUEST['a'] == 'checkout'){
        $event = 'begin_checkout';
        $action = 'checkout';
      }
      break;

  }

  $js_events = '';

  if ($action === 'viewcart'){

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

  $currencyPrefix = '$'; //default, get live currency prefix below
  foreach ( $vars['currencies'] as $currency ){
    if ($currency['code'] === $currencyCode){
      $currencyPrefix = $currency['prefix']; 
    }
  }
  
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
      'quantity'       => 1
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


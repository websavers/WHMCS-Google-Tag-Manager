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

function gtm_format_price($price, $currencyCode){
  $price = str_replace("$","",$price);
  return str_replace($currencyCode, "",$price);
}

/** The following two hooks output the code required for GTM to function **/

add_hook('ClientAreaHeadOutput', 1, function($vars) {
  $container_id = gtm_get_module_settings('gtm-container-id');
  if (!empty($container_id)):
    return "<!-- Google Tag Manager -->
<script>dataLayer = [];</script>
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
  
  $productAdded = $vars['productinfo'];
  $domainsAdded = $vars['domains'];
  $productsAdded = $vars['products']; 
  
  $currencyCode = $vars['activeCurrency']['code'];
  $lang = $vars['activeLocale']['languageCode'];
  
  //if ( $_REQUEST['debug'] ) var_dump($vars['activeCurrency']['code']); ///DEBUG
  
  $productsArray = array();
  
  if (!empty($productAdded)){ //product config
    $selectedCycle = $vars['billingcycle'];
    $price = (string)$vars['pricing']['rawpricing'][$selectedCycle];

    $productsArray[] = array(
      'name'      => $productAdded['name'],
      'id'        => $productAdded['pid'],
      'price'     => $price,
      'category'  => $productAdded['group_name'],
      'quantity'  => 1
    );
  }
  if (!empty($domainsAdded)){ //domain config
    foreach($domainsAdded as $domain){
      $productsArray[] = array(                        
        'name'      => "Domain: " . $domain['domain'],
        'price'     => gtm_format_price($domain['price'], $currencyCode),
        'category'  => 'Domain Registration',
        'quantity'  => 1
      );
    }
  }
  if (!empty($productsAdded)){ //viewcart
    foreach($productsAdded as $productAdded){
      $selectedCycle = $productAdded['billingcyclefriendly'];
      $price = (string)$productAdded['pricing']['recurring'][$selectedCycle];
      $productsArray[] = array(                       
        'name'      => $productAdded['productinfo']['name'],
        'id'        => $productAdded['productinfo']['pid'],
        'price'     => gtm_format_price($price, $currencyCode),
        'category'  => $productAdded['productinfo']['groupname'],
        'quantity'  => 1
      );
    }
  }
  
  $commonArray = array(
    'currencyCode'  => $currencyCode,
    'language'      => $lang,
    'template'      => $vars['templatefile'],
    'userID'        => $vars['userid']
  );
  
  $eventArray = array();
  
  switch ($vars['templatefile']){
		
    case 'configureproductdomain':
      $eventArray = array(
        'event'         => 'checkout',
        'eventAction'   => 'ConfigureProductDomain',
        'ecommerce'     => array(
          'checkout' => array(
            'actionField' => array( 'step' => 1, 'option' => 'ConfigureProductDomain' )
          ),
          'products' => $productsArray
        )
      );
      break;
      
    case 'configureproduct':
      $eventArray = array(
        'event'         => 'checkout',
        'eventAction'   => 'ConfigureProduct',
        'ecommerce'     => array(
          'checkout' => array( 
            'actionField' => array( 'step' => 2, 'option' => 'ConfigureProduct' )
          ),
          'products' => $productsArray
        )
      );
      break;
      
    case 'configuredomains':
      $eventArray = array(
        'event'         => 'checkout',
        'eventAction'   => 'ConfigureDomains',
        'ecommerce'     => array(
          'checkout' => array( 
            'actionField' => array( 'step' => 3, 'option' => 'ConfigureDomains' )
          ),
          'products' => $productsArray
        )
      );
      break; 
      
    case 'viewcart':
      if ($_REQUEST['a'] == 'view'){
        $eventArray = array(
          'event'         => 'checkout',
          'eventAction'   => 'ViewCart',
          'ecommerce'     => array(
            'checkout' => array( 
              'actionField' => array( 'step' => 4, 'option' => 'ViewCart' )
            ),
            'products' => $productsArray
          )
        );
      }
      else if ($_REQUEST['a'] == 'checkout'){
        $eventArray = array(
          'event'         => 'checkout',
          'eventAction'   => 'Checkout',
          'ecommerce'     => array(
            'checkout' => array( 
              'actionField' => array( 'step' => 5, 'option' => 'Checkout' )
            ),
            'products' => $productsArray
          )
        );
      }
      break;
  }

  if (!empty($eventArray))
    return "<script id='GTM_DataLayer'>
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push(" . json_encode(array_merge($eventArray, $commonArray)) . ");
    </script>";
});

/**
 * https://developers.whmcs.com/hooks-reference/shopping-cart/#shoppingcartcheckoutcompletepage
 */
add_hook('ShoppingCartCheckoutCompletePage', 1, function($vars) {
  
  $currencyCode = $vars['activeCurrency']['code'];
  $lang = $vars['activeLocale']['languageCode'];
  
  $res_orders = localAPI('GetOrders', array('id' => $vars['orderid']));
  $order = $res_orders['orders']['order'];

  $productsArray = array();
  foreach ($order['lineitems']['lineitem'] as $product){
    $productsArray[] = array(
      'name'      => $product['product'],
      'id'        => $product['relid'],
      'price'     => $product['amount'],
      'category'  => $product['producttype'],
      'quantity'  => 1
    );
  }
  
  $res_invoice = localAPI('GetInvoice', array('invoiceid' => $order['invoiceid']));
  $tax = (float)$res_invoice['tax'] + (float)$res_invoice['tax2'];
  
  $eventArray = array(
    'event'       => 'checkout',
    'eventAction' => 'PaymentComplete',
    'currencyCode'  => $currencyCode,
    'language'      => $lang,
    'template'      => $vars['templatefile'],
    'userID'        => $vars['clientdetails']['userid'],
    'ecommerce'   => array(
      'checkout' => array(
        'actionField' => array('step' => 6, 'option' => 'PaymentComplete')
      ),
      'purchase'  => array(
        'actionField' => array(
          'id'        => $order['id'], 
          'revenue'   => $order['amount'], // Total transaction value (incl. tax and shipping)
          'tax'       => $tax,
          'coupon'    => $order['promocode']
        ),
        'products'    => $productsArray
      )
    )
  );

  if (!empty($eventArray)){
    return "<script id='GTM_DataLayer'>window.dataLayer = window.dataLayer || []; 
    window.dataLayer.push({ ecommerce: null }); 
    window.dataLayer.push(" . json_encode($eventArray) . ");
    </script>";
  }
  
});


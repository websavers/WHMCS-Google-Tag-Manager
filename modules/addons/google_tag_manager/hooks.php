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
  return str_replace(['$', $currencyCode],'',$price);
}

function gtm_ga_module_in_use(){
  $ga_site_tag = Capsule::table('tbladdonmodules')
        ->where('module', 'google_analytics')
        ->where('setting', 'code')
        ->value('value');
        
  $active_addons = Capsule::table('tblconfiguration')
        ->where('setting', 'ActiveAddonModules')
        ->value('value');
        
  $is_active = (strpos($active_addons, 'google_analytics') !== false)? true:false;
        
  return (empty($ga_site_tag) && !$is_active)? false:true;
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
      'price'     => gtm_format_price($price, $currencyCode),
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
  $addToCart = false;
  
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
      $addToCart = true;
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
      $addToCart = true;
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
  
  $addToCartOutput = '';
  if ($addToCart){
    $addToCartOutput = "window.dataLayer.push(" . json_encode(array(
      'event'     => 'addToCart',
      'ecommerce' => array(
        'currencyCode' => $currencyCode,
        'add'          => array('products' => $productsArray)
      )
    )) . ");";
  }

  if (!empty($eventArray)){
    return "<script id='GTM_DataLayer'>
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push(" . json_encode(array_merge($eventArray, $commonArray)) . ");
    " . $addToCartOutput . "
    </script>";
  }
});

/**
 * https://developers.whmcs.com/hooks-reference/shopping-cart/#shoppingcartcheckoutcompletepage
 */
add_hook('ShoppingCartCheckoutCompletePage', 1, function($vars) {
  
  /* Built in GA module handles the purchase event for us */
  if (gtm_ga_module_in_use()) return '';
    
  $res_orders = localAPI('GetOrders', array('id' => $vars['orderid']));
  $order = $res_orders['orders']['order'][0];
  
  $currencyCode = $order['currencysuffix'];
  $lang = $vars['activeLocale']['languageCode']; //var does not exist
  
  //if ( $_REQUEST['debug'] ) var_dump($order); ///DEBUG
  
  $checkoutEventArray = array(
    'event'         => 'checkout',
    'eventAction'   => 'PaymentComplete',
    'value'         => $order['amount'], //for standard event tracking
    'currencyCode'  => $currencyCode,
    'language'      => $lang,
    'template'      => 'complete',
    'userID'        => $order['userid'],
    'ecommerce'   => array(
      'checkout' => array(
        'actionField' => array('step' => 6, 'option' => 'PaymentComplete')
      )
    )
  );
  
  $productsArray = array();
  foreach ($order['lineitems']['lineitem'] as $product){
    $productsArray[] = array(
      'name'      => $product['product'],
      'id'        => $product['relid'],
      'price'     => gtm_format_price($product['amount'], $currencyCode),
      'brand'     => 'Websavers',
      'category'  => $product['producttype'],
      'quantity'  => 1,
      'coupon'    => ''
    );
  }
  
  $res_invoice = localAPI('GetInvoice', array('invoiceid' => $order['invoiceid']));
  $tax = (float)$res_invoice['tax'] + (float)$res_invoice['tax2'];
  
  $purchaseEventArray = array(
    'ecommerce'   => array(
      'currencyCode'  => $currencyCode,
      'purchase'  => array( //for enhanced ecommerce tracking
        'actionField'   => array(
          'id'          => $order['id'],
          'affiliation' => 'WHMCS Orderform',
          'revenue'     => $order['amount'], // Total transaction value (incl. tax and shipping)
          'tax'         => $tax,
          'coupon'      => $order['promocode']
        ),
        'products' => $productsArray
      )
    )
  );

  return "<script id='GTM_DataLayer'>window.dataLayer = window.dataLayer || []; 
  window.dataLayer.push({ ecommerce: null }); 
  window.dataLayer.push(" . json_encode($checkoutEventArray) . ");
  window.dataLayer.push({ ecommerce: null }); 
  window.dataLayer.push(" . json_encode($purchaseEventArray) . ");
  </script>";
  
});


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
  
  $productJSON = '';
  if (!empty($productAdded)){ //product config
    
    $selectedCycle = $vars['billingcycle'];
    $price = $vars['pricing']['rawpricing'][$selectedCycle];
    
    $productJSON = "{                        
      'name': '{$productAdded[name]}',
      'id': '{$productAdded[pid]}',
      'price': '$price',
      'category': '{$productAdded[group_name]}',
      'quantity': 1
    }";
  }
  if (!empty($domainsAdded)){ //domain config
    foreach($domainsAdded as $domain){
      $productJSON .= "{                        
        'name': 'Domain: {$domain[domain]}',
        'price': '{$domain[price]}',
        'category': 'Domain',
        'quantity': 1
      },";
    }
  }
  if (!empty($productsAdded)){ //viewcart
    foreach($productsAdded as $productAdded){
      $selectedCycle = $productAdded['billingcyclefriendly'];
      $price = $productAdded['pricing']['recurring'][$selectedCycle];
      $productJSON .= "{                        
        'name': '{$productAdded[productinfo][name]}',
        'id': '{$productAdded[productinfo][pid]}',
        'price': '$price',
        'category': '{$productAdded[productinfo][groupname]}',
        'quantity': 1
      },";
    }
  }
  
  $common = "
      'currencyCode': '$currencyCode',
      'language': '$lang',
      'template': '{$vars[templatefile]}',
      'userID': '{$vars[userid]}'
      ";
  
  switch ($vars['templatefile']){
		
    case 'configureproductdomain':
      $eventJSON = "{
        'event': 'checkout',
        'ecommerce': {
          'checkout': {
            'actionField': {'step': 1, 'option': 'ConfigureProductDomain'},
            'products': [$productJSON]
          }
        },
        $common
      }";
      break;
    case 'configureproduct':
      $eventJSON = "{
        'event': 'checkout',
        'ecommerce': {
          'checkout': {
            'actionField': {'step': 2, 'option': 'ConfigureProduct'},
            'products': [$productJSON]
          }
        },
        $common
      }";
      $eventJSON .= ",{
        'event': 'addToCart',
        'ecommerce': {
          'currencyCode': '$currencyCode',
          'add': {                                
            'products': [$productJSON]
          }
        },
        $common
      }";
      break;
    case 'configuredomains':
      $eventJSON = "{
        'event': 'checkout',
        'ecommerce': {
          'checkout': {
            'actionField': {'step': 3, 'option': 'ConfigureDomains'},
            'products': [$productJSON]
          }
        },
        $common
      }";
      break; 
    case 'viewcart':
      if ($_REQUEST['a'] == 'view'){
        $eventJSON = "{
          'event': 'checkout',
          'ecommerce': {
            'checkout': {
              'actionField': {'step': 4, 'option': 'ViewCart'},
              'products': [$productJSON]
            }
          },
          $common
        }";
      }
      else if ($_REQUEST['a'] == 'checkout'){
        $eventJSON = "{
          'event': 'checkout',
          'ecommerce': {
            'checkout': {
              'actionField': {'step': 5, 'option': 'Checkout'},
              'products': [$productJSON]
            }
          },
          $common
        }"; 
      }
      break; 
  }

  if (!empty($eventJSON))
    return "<script id='GTM_DataLayer'>
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push($eventJSON);
    </script>";
});

/**
 * https://developers.whmcs.com/hooks-reference/shopping-cart/#shoppingcartcheckoutcompletepage
 */
add_hook('ShoppingCartCheckoutCompletePage', 1, function($vars) {
  
  $userId = $vars['clientdetails']['userid'];
  
  $result = localAPI('GetOrders', array('id' => $vars['orderid']));
  $order = $result['orders']['order'];
  
  $productsJSON = '';
  foreach ($order['lineitems']['lineitem'] as $product){
    $productsJSON .= "{
      'name': '{$product[product]}',
      'id': '{$product[relid]}',
      'price': '{$product[amount]}',
      'category': '{$product[producttype]}',
      'quantity': 1,
    },";
  }
		
  $eventJSON = "{
    'event': 'checkout',
    'ecommerce': {
      'checkout': {
        'actionField': {'step': 6, 'option': 'PaymentComplete'},
      }
      'purchase': {
        'actionField': {
          'id': '{$order[id]}', 
          'revenue': '{$order[amount]}',       // Total transaction value (incl. tax and shipping)
          'tax':'',
          'coupon': '{$order[promocode]}'
        },
        'products': [$productsJSON]
      }
    }
  }";

  if (!empty($eventJSON)){
    return "<script id='GTM_DataLayer'>window.dataLayer = window.dataLayer || []; 
    window.dataLayer.push({ ecommerce: null }); 
    window.dataLayer.push($eventJSON);
    </script>";
  }
  
});


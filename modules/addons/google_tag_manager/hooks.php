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

		return '
		<script id="GTM_DataLayer">
		
			document.querySelectorAll("#inputNewPassword1, #inputNewPassword2, #inputEmail").forEach(field => {
				field.setAttribute("required", "");
			});
			
			document.querySelector("form#frmCheckout input[type=\"submit\"]").onclick = function(e) {
				e.preventDefault();

				const register_form 		= document.getElementById("frmCheckout");
				const inputCountry			= document.querySelector("#inputCountry");
				let first_name              = document.querySelector("#inputFirstName").value;
				let last_name               = document.querySelector("#inputLastName").value;
				let email_address           = document.querySelector("#inputEmail").value;
				let phone_number            = document.querySelector("#inputPhone").value.replace(/\\s+/g, "");
				let phone_country_code      = document.querySelector(".selected-dial-code").innerHTML;
				let city                    = document.querySelector("#inputCity").value;
				let state                   = document.querySelector("#stateinput").value;
				let country                 = inputCountry.options[inputCountry.selectedIndex].text;
				let postal_code             = document.querySelector("#inputPostcode").value;
				let street_address          = document.querySelector("#inputAddress1").value;

				let company_name            = document.querySelector("#inputCompanyName").value;
				let street_address_2        = document.querySelector("#inputAddress2").value;

        if (first_name && last_name && email_address && phone_number){

          signupEvent = {
            event: "sign_up",
            signupData: {
              method: "WHMCS",
              first_name: first_name,
              last_name: last_name,
              email_address: email_address,
              phone_number: phone_number,
              phone_country_code: phone_country_code,
              street_address: street_address,
              city: city,
              state: state,
              country: country,
              postal_code: postal_code,
            }
          }

          // Add to Data Layer if available
          if(company_name){ signupEvent.signupData.company_name = company_name; }
          if(street_address_2){ signupEvent.signupData.street_address_2 = street_address_2; }

          // Submit event to Google
          dataLayer.push(signupEvent);

        }

        // Submit form normally
				register_form.submit();

			}

		</script>
		';
	});

});
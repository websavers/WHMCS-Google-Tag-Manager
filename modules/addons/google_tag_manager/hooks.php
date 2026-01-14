<?php
/**
 * WHMCS Google Tag Manager Module Hooks File
 *
 * @copyright Copyright (c) Websavers Inc 2021-2024
 * @license LICENSE file included in this package
 */

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) die("This file cannot be accessed directly");

define("MODULENAME", 'google_tag_manager');

/**
 * Optimized settings retrieval with static caching
 */
function gtm_get_module_settings($setting = null) {
    static $settings = null;
    if (is_null($settings)) {
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', MODULENAME)
            ->pluck('value', 'setting')
            ->toArray();
    }

    if ($setting === null) return $settings;
    return isset($settings[$setting]) ? $settings[$setting] : '';
}

/**
 * Clean price strings for numeric processing
 */
function gtm_format_price($price, $currencyCode, $prefix) {
    // Remove symbols, currency codes, spaces, and commas
    $cleanPrice = str_ireplace([$prefix, ',', ' ', $currencyCode], ['', '.', '', ''], $price);
    // Force to numeric
    return (float)filter_var($cleanPrice, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

/**
 * Header Hooks: Output GTM Snippets
 */
add_hook('ClientAreaHeadOutput', 1, function($vars) {
    $container_id = gtm_get_module_settings('gtm-container-id');
    if (!empty($container_id)) {
        return "<script>window.dataLayer = window.dataLayer || [];</script>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','$container_id');</script>
";
    }
});

add_hook('ClientAreaHeaderOutput', 1, function($vars) {
    $container_id = gtm_get_module_settings('gtm-container-id');
    if (!empty($container_id)) {
        return "<noscript><iframe src='https://www.googletagmanager.com/ns.html?id=$container_id'
height='0' width='0' style='display:none;visibility:hidden'></iframe></noscript>
";
    }
});

/**
 * DataLayer Event Output (Footer)
 */
add_hook('ClientAreaFooterOutput', 1, function($vars) {

    if (gtm_get_module_settings('gtm-enable-datalayer') == 'off') return '';

    $currency = $vars['activeCurrency'];
    $currencyCode = $currency->code;
    $currencyPrefix = $currency->prefix;

    $itemsArray = [];
    $cartMap = [];
    $js_events = '';
    $event = '';
    $action = '';

    // Global listener for "Order Now" buttons on store pages
    $js_events .= '
        document.querySelectorAll(".btn-order-now").forEach(function(btn) {
            btn.addEventListener("click", function() {
                dataLayer.push({ ecommerce: null });
                dataLayer.push({
                    event: "add_to_cart",
                    ecommerce: { items: [{ item_name: "Product Selection", quantity: 1 }] }
                });
            });
        });
    ';

    switch($vars['templatefile']) {

        case 'configureproduct':
            $productAdded = $vars['productinfo'];
            $selectedCycle = $vars['billingcycle'];
            $price = ($vars['pricing']['type'] == "onetime")
                ? (string)$vars['pricing']['minprice']['simple']
                : (string)$vars['pricing']['rawpricing'][$selectedCycle];

            $item = [
                'item_name'     => htmlspecialchars_decode($productAdded['name']),
                'item_id'       => $productAdded['pid'],
                'price'         => gtm_format_price($price, $currencyCode, $currencyPrefix),
                'item_category' => $productAdded['group_name'],
                'item_variant'  => $selectedCycle,
                'quantity'      => 1,
                'currency'      => trim($currencyCode)
            ];

            $itemsArray[] = $item;
            $event = 'view_item';
            $action = 'configureproduct';

            $js_events .= '
            var btnConfig = document.getElementById("btnCompleteProductConfig");
            if (btnConfig) {
                btnConfig.addEventListener("click", function() {
                    dataLayer.push({ ecommerce: null });
                    dataLayer.push({
                        event: "add_to_cart",
                        ecommerce: { items: [' . json_encode($item) . '] }
                    });
                });
            }';
            break;

        case 'configuredomains':
            if (is_array($vars['domains'])) {
                foreach($vars['domains'] as $domain) {
                    // Get the TLD (e.g., .nl)
                    $domainParts = explode('.', $domain['domain']);
                    $extension = '.' . end($domainParts);

                    // Fetch the 1-year registration price from the 'annually' column
                    $tldPricing = Capsule::table('tblpricing')
                        ->join('tbldomainpricing', 'tbldomainpricing.id', '=', 'tblpricing.relid')
                        ->where('tbldomainpricing.extension', $extension)
                        ->where('tblpricing.type', 'domainregister')
                        ->where('tblpricing.currency', (int)$vars['activeCurrency']['id'])
                        ->value('msetupfee'); // Using 'annually' based on your table describe

                    $itemsArray[] = [
                        'item_name'     => 'Domain Registration',
                        'item_id'       => $domain['domain'],
                        'price'         => (float)$tldPricing,
                        'item_category' => 'Domain',
                        'item_variant'  => 'annually',
                        'quantity'      => 1,
                        'currency'      => trim($currencyCode)
                    ];
                }
            }
            $event = 'view_item';
            $action = 'configuredomains';

            $js_events .= '
            var domainForm = document.getElementById("frmConfigureDomains");
            if (domainForm) {
                domainForm.addEventListener("submit", function() {
                    dataLayer.push({ ecommerce: null });
                    dataLayer.push({
                        event: "add_to_cart",
                        ecommerce: { items: ' . json_encode($itemsArray) . ' }
                    });
                });
            }';
            break;

        case 'viewcart':
            // 1. Map Products
            foreach($vars['products'] as $key => $productAdded) {
                $price = $productAdded['pricing']['baseprice'];
                if (is_object($price)) $price = $price->toNumeric();

                $item = [
                    'item_name'     => htmlspecialchars_decode($productAdded['productinfo']['name']),
                    'item_id'       => $productAdded['productinfo']['pid'],
                    'price'         => $price,
                    'item_category' => $productAdded['productinfo']['groupname'],
                    'item_variant'  => $productAdded['billingcycle'],
                    'quantity'      => 1,
                    'currency'      => trim($currencyCode)
                ];
                $itemsArray[] = $item;
                $cartMap['p' . $key] = $item;

                foreach ($productAdded['addons'] as $ak => $addon) {
                    $aPrice = $addon['pricingtext'];
                    if (is_object($aPrice)) $aPrice = $aPrice->toNumeric();
                    $addonItem = [
                        'item_name'     => htmlspecialchars_decode($addon['name']),
                        'item_id'       => $addon['addonid'],
                        'price'         => $aPrice,
                        'item_category' => 'Addon',
                        'quantity'      => 1,
                        'currency'      => trim($currencyCode)
                    ];
                    $itemsArray[] = $addonItem;
                    $cartMap['a' . $ak] = $addonItem;
                }
            }

            // 2. Map Domains
            if (is_array($vars['domains'])) {
                foreach($vars['domains'] as $key => $domain) {
                    $item = [
                        'item_name'     => 'Domain Registration',
                        'item_id'       => $domain['domain'],
                        'price'         => gtm_format_price($domain['price'], $currencyCode, $currencyPrefix),
                        'item_category' => 'Domain',
                        'item_variant'  => $domain['regperiod'] . ' Year(s)',
                        'quantity'      => 1,
                        'currency'      => trim($currencyCode)
                    ];

                    $itemsArray[] = $item;

                    // ADD THIS LINE: Map the domain using 'd' + index
                    $cartMap['d' . $key] = $item;
                }
            }

            if ($_REQUEST['a'] == 'view') {
                $event = 'view_cart';
                $action = 'viewcart';
            } else if ($_REQUEST['a'] == 'checkout') {
                $event = 'begin_checkout';
                $action = 'checkout';
            }

            $js_events .= '
            var cartMap = ' . json_encode($cartMap) . ';
            // Product Removal
            document.querySelectorAll(".btn-remove-from-cart").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var clickAct = this.getAttribute("onclick");
                    var match = clickAct.match(/\(\'(\w+)\'\,\'(\d+)\'\)/);
                    if (match) {
                        var key = match[1] + match[2];
                        if (cartMap[key]) {
                            dataLayer.push({ ecommerce: null });
                            dataLayer.push({
                                event: "remove_from_cart",
                                ecommerce: { items: [cartMap[key]] }
                            });
                        }
                    }
                });
            });'
            ;
            break;
    }

    $output = '';
    if (!empty($itemsArray) && !empty($event)) {
        $eventArray = [
            'event'       => $event,
            'eventAction' => $action,
            'ecommerce'   => ['items' => $itemsArray]
        ];
        $output .= "
        <script id='GTM_DataLayer'>
            window.dataLayer = window.dataLayer || [];
            dataLayer.push({ ecommerce: null });
            dataLayer.push(" . json_encode($eventArray) . ");
        </script>";
    }

    $output .= "<script id='GTM_JS_Events'>
        document.addEventListener('DOMContentLoaded', function() {
            " . $js_events . "
        });
    </script>";

    return $output;
});

/**
 * Purchase Tracking
 */
add_hook('ShoppingCartCheckoutCompletePage', 1, function($vars) {
    if (gtm_get_module_settings('gtm-enable-datalayer') == 'off') return '';

    $res_orders = localAPI('GetOrders', ['id' => $vars['orderid']]);
    $order = $res_orders['orders']['order'][0];
    $currencyCode = $order['currencysuffix'];
    $currencyPrefix = $order['currencyprefix'];

    $itemsArray = [];
    foreach ($order['lineitems']['lineitem'] as $product) {
        $p_g_n = explode(' - ', $product['product']);
        $category = (count($p_g_n) > 1) ? $p_g_n[0] : '';
        $name = (count($p_g_n) > 1) ? $p_g_n[1] : $product['product'];

        $itemsArray[] = [
            'item_name'     => $name,
            'item_id'       => $product['relid'],
            'price'         => gtm_format_price($product['amount'], $currencyCode, $currencyPrefix),
            'item_category' => $category,
            'item_variant'  => $product['billingcycle'],
            'quantity'      => 1,
            'currency'      => trim($currencyCode)
        ];
    }

    $res_invoice = localAPI('GetInvoice', ['invoiceid' => $order['invoiceid']]);
    $tax = (float)$res_invoice['tax'] + (float)$res_invoice['tax2'];

    $eventArray = [
        'event' => 'purchase',
        'ecommerce' => [
            'transaction_id' => $order['id'],
            'affiliation'    => 'WHMCS Orderform',
            'value'          => $order['amount'],
            'tax'            => $tax,
            'shipping'       => 0,
            'currency'       => trim($currencyCode),
            'coupon'         => $order['promocode'],
            'items'          => $itemsArray
        ]
    ];

    return "<script id='GTM_DataLayer'>
        dataLayer.push({ ecommerce: null });
        dataLayer.push(" . json_encode($eventArray) . ");
    </script>";
});

/**
 * Registration / Sign Up Tracking
 */
add_hook('ClientAreaPageRegister', 1, function($vars) {
    if (gtm_get_module_settings('gtm-enable-datalayer') == 'off') return '';

    add_hook('ClientAreaFooterOutput', 1, function($vars) {
        return '
       <script id="GTM_DataLayer_Register">
          document.addEventListener("DOMContentLoaded", function() {
              var regForm = document.getElementById("frmCheckout");
              if (!regForm) return;

              regForm.addEventListener("submit", function(e) {
                  var email = document.querySelector("#inputEmail").value;
                  if (email) {
                      dataLayer.push({
                          event: "sign_up",
                          signupData: {
                              method: "WHMCS",
                              email_address: email,
                              country: document.querySelector("#inputCountry").value
                          }
                      });
                  }
              });
          });
       </script>';
    });
});
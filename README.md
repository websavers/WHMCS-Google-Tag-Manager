# WHMCS Google Tag Manager #

## Summary ##

This WHMCS module automatically inserts GTM embed codes in the Client Area and provides 
the necessary JavaScript dataLayer eComm variables

## Details ##

There is no admin or client area views for this module.

## Installation & Configuration ##

- Install module using the standard method: https://docs.whmcs.com/Addon_Modules_Management#Installing_An_Addon
- At step 4 of the WHMCS installation steps ("Configure"), you'll see the spot
to enter your Google Tag Manager Container ID. Enter it and save changes. 
- Connect GTM to Google Analytics as per the Google guide. Be sure to enable the Ecommerce option. https://support.google.com/tagmanager/answer/6107124?hl=en
- Enable Ecommerce tracking in Google Analytics as per the WHMCS guide: https://docs.whmcs.com/Google_Analytics

## Google Analytics Ecommerce Steps ##

Configuring these steps will allow you to track customers as they proceed through
the checkout steps. This module automatically sends necessary data to Analytics, 
and so these steps will show you how to configure Analytics to organize that data.

There is a step for each page of the checkout process as you can see below.

In GA go to Ecommerce settings and ensure both toggles under set-up are ON. Under 
Checkout Labeling create the FUNNEL STEPS as follows:

1. ConfigureProductDomain
2. ConfigureProduct
3. ConfigureDomains
4. ViewCart
5. Checkout
6. PaymentComplete

Press Save.


## Minimum Requirements ##

Only tested with WHMCS 8.3 and newer. 

This is an open source module. Please do not request features be added, however 
we welcome you to look through the code, add whatever features you want and fix 
any bugs you see, then create a pull request for it to be included in core.

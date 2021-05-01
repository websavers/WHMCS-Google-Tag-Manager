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
- Connect GTM to Google Analytics as per the Google guide. Be sure you're 
connecting to a Google Analytics 4 property ID

## Google Analytics Ecommerce Steps ##

{Update here with GA4 configuration.}

## Developer Notes ##

The documentation to follow for all dataLayer events can be found here: 
https://developers.google.com/tag-manager/ecommerce-ga4

## Minimum Requirements ##

Only tested with WHMCS 8.3 and newer. 

This is an open source module. Please do not request features be added, however 
we welcome you to look through the code, add whatever features you want and fix 
any bugs you see, then create a pull request for it to be included in core.

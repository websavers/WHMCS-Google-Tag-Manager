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

## Google Analytics 4 Ecommerce Integration ##

Begin by following the Google's Analytics configuration guide for Tag Manager: https://support.google.com/tagmanager/answer/9442095?hl=en

Then create the GA 4 Event Tags as follows:
- Tag Type: Google Analytics 4 Events
- More Settings > Ecommerce > Send Ecommerce data (with the Data source set to Data Layer)
- Triggering: Create a trigger with a custom event where the event name is set to `view_item_list|select_item|view_item|add_to_cart|begin_checkout|purchase` and enable regex matching. This allows each event name to be triggered separately.

List of event tag names actively used:

1. View Item Event. Event Name: view_item
2. Add to Cart Event. Event Name: add_to_cart
3. Begin Checkout Event. Event Name: begin_checkout
4. Purchase Event. Event Name: purchase
5. Sign Up Event. Event Name: sign_up

The "Event Name" values will match with the HTML/JS markup this module generates, and enabling the Ecommerce data via Data Layer will ensure it captures the ecommerce data within that markup. This data will automatically fill out your Analytics report under Monetization > Ecommerce purchases.

Google's general guide to GA 4 event tags in tag manager can be found here: https://support.google.com/tagmanager/answer/13034206

## Developer Notes ##

The documentation to follow for all dataLayer events can be found here: 
https://developers.google.com/tag-manager/ecommerce-ga4

## Minimum Requirements ##

Only tested with WHMCS 8.3 and newer. Tested working up to WHMCS 8.8.0

This is an open source module provided free of charge and without support. Please do not request features be added, however we welcome you to look through the code, add whatever features you want and fix any bugs you see, then create a pull request for it to be included in core.

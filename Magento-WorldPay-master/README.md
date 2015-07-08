WorldPay Extension
==================

The extension provides WorldPay payment integration which enables payment processing through an existing merchant account. The extension supports secure credit card payment ("Select Junior") including callback functionality (see documentation for configuration details). No PCI DSS Certification is needed, since all payment data is transmitted via WorldPay's payment form.

To configure dynamic payment response support for multi-store setups see Worldpay knowledge base documentation.

This extension has been developed by PHOENIX MEDIA, Magento Gold Partner in Germany and Austria.


Changelog
---------

Changes starting from version 1.4 can be found in the "Release Notes" tab on this page.

1.3.1:
- fixed minor translation issues
- added missing features 

1.3.0:
- support for new signature hash
- save fraud information in database
- hide language switch option
- debug option 

1.2.6:
- saving credit card type from payment response 

1.2.5:
- Fixed wrong helper call 

1.2.4:
- Fixed issue with shopping card reload on cancelation (Magento 1.4.1)
- Code cleanup 

1.2.3:
- Minor fix for Magento 1.4.1 

1.2.2:
- Minor fix for Magento 1.4

1.2.1:
- Added callback IP validation
- Added minor fix for Magento 1.4

1.2.0:
- Remote admin functionalities for capture and refund in the backend
- Rework of the processing controller
- Cancel payment now reloads shopping cart correctly
- Fixes session issue during callback
- AVS status is now visible in the backend

1.1.3:
- Added new WorldPay URLs

1.1.2:
- Removed Multishipping Checkout support (redirect doesn't work for multiple orders)

1.1.1:
- Added hideCurrency parameter to prevent issues on the payment verification.

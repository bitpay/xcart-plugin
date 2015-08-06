# Using the BitPay plugin for XCart

## Prerequisites
You must have a BitPay merchant account to use this plugin.  It's free to [sign-up for a BitPay merchant account](https://bitpay.com/start).


## Installation of .tar file downloaded from bitpay.com or a release

- Download BitPay's latest .tar file bitpay.com or from the latest release.
- Go to the XCart admin Panel, and click on Modules.
- Click Upload add-on, and choose the BitPay .tar file to upload. Click Install add-on.
- The plugin will install and you'll be redirected to the Installed Modules page.
- Check Enabled under the BitPay plugin. Then click Save Changes.
- Go to Store setup in your admin panel.
- Click Add payment method.
- Search for BitPay, and click add on the BitPay Payment method.
- BitPay should now be visible under your Store setup > Payment Settings page.
- Make sure BitPay is listed as Active so that customers can pay using Bitcoin.

## Configuration

* Log into the XCart admin panel, then go to Store Setup. Under the BitPay Payment Method click Configure.

![BTC Invoice](https://raw.githubusercontent.com/aleitner/aleitner.github.io/master/xcart/xcart%20Payment%20Method.png)

* Change the Transaction Speed if desired.  Can be `high`, `medium`, or `low`.  HIGH speed confirmations typically take 5-10 seconds, and can be used for digital goods or low-risk items. LOW speed confirmations take about 1 hour, and should be used for high-value items.

![BTC Invoice](https://raw.githubusercontent.com/aleitner/aleitner.github.io/master/xcart/xcart%20Settings.png)

* You will need to Pair with BitPay in order to generate invoices..
  * If you want to generate real invoices with  **bitpay.com** set the dropdown next to Connect with BitPay to **live**.
  * If you want to generate fake invoices with **test.bitpay.com** set the dropdown next to Connect with BitPay to **test**.
* Click Connect with BitPay to pair with BitPay and create a token for doing transactions. You must Approve the token on your BitPay account.

![BTC Invoice](https://raw.githubusercontent.com/aleitner/aleitner.github.io/master/xcart/xcart%20Pair.png)

* You should now see a paired token in your settings. 

![BTC Invoice](https://raw.githubusercontent.com/aleitner/aleitner.github.io/master/xcart/xcart%20Paired.png)

* Click Update below.

## Usage

- Once the configuration is done, whenever a buyer selects Bitcoins as their payment method an invoice is generated at bitpay.com

# Magento 2 Mobbex Payment Gateway

## Installation

### Using Composer: 
1. Run the following commands in the Magento installation folder
    ```
    composer require mobbexco/magento-2
    php bin/magento setup:upgrade
    php bin/magento setup:static-content:deploy -f
    ```

### Manually: 
1. Create the following directory in the Magento installation folder

    ```
    app/code/Mobbex/Webpay
    ```
2. Copy all files in that directory
3. Run the following  commands in the Magento installation folder
    ```
    php bin/magento module:enable Mobbex_Webpay
    php bin/magento setup:di:compile
    php bin/magento setup:upgrade
    php bin/magento setup:static-content:deploy -f
    ```

## Changelog:

2021-11-30: 2.2.2
- Require laminas log package to support new magento versions

2021-10-21: 2.2.1
- Fix checkout body generation

2021-10-20: 2.2.0
- New finance widget fully rendered from plugin
- Fix duplicated order status updates

2021-09-22: 2.1.5
- Improve finance widget events isolation
- Refactor and some fixes on plans filter and installments obtaining
- Update embed sdk version to 1.0.20

2021-09-17: 2.1.4
- Add new internal hook for webhook receive
- Add disable invoice generation option
- Update total paid order item on discounts

2021-08-26: 2.1.3
- Fix discount and finnancial charge save

2021-08-23: 2.1.2
- Support Mobbex Marketplace extension module
- Fix order info page translations
- Fix wallet card rendering and installments obtaining

2021-08-03: 2.1.1
- Fix check that checkout fields are defined
- Fix translation function uses
- Embed payment is enabled by default

2021-06-18: 2.1.0
- New option to add DNI field in checkout
- Fix advanced plans condition

2021-06-11: 2.0.1
- Fix order mail sending

2021-05-19: 2.0.0
- Introduced Mobbex Wallet for logged in users

2021-05-17: 1.4.2
- Fix refund observer payment method check
- Fix financial widget price get
- Improved financial widget IDs uniqueness

2021-04-19: 1.4.1
- Add checks to CUIT obtaining

2021-03-28: 1.4.0
- Add common/advanced plans filter by category
- Some improvements

2021-03-23: 1.3.4
- Hide order URL (Mobbex console) from frontend
- Fix js error on product view
- Fix order status config on payment fail
- Fix checkout boolean options (Test and Embed modes)

2021-02-05: 1.3.3
- Supports to refunds created by api
- Fix admin save product when plugin is not configured

2021-01-29: 1.3.2
- Show version in plugin settings
- Fix checkout title config get
- Fix checkout custom fields error

2021-01-26: 1.3.1
- Fix financial widget configs getters

2021-01-22: 1.3.0
- Add financing widget to product view
- Add common/advanced plans filter by product
- Add Custom Field model to improve database management
- Fix shipping tax obtaining

2021-01-19: 1.2.1
- Fix items total obtaining
- Cancel order to return stock when payment is not attempted

2020-12-28: 1.2.0
- Save and show financial cost in store
- Add support for online refunds
- Add error messages
- Mobbex returns to checkout when payment fails
- Add option to show banner on checkout
- Add helper for config
- Add translation file
- Add email and order status settings

2020-12-18: 1.1.10
- Fix obtaining price for order items

2020-12-16: 1.1.9
- Fix handling of order hold states

2020-12-15: 1.1.8
- Improvements in handling of order states
- Remove redundancies
- Fixes

2020-12-10: 1.1.7
- Add option to install using composer
- Fix composer.json

2020-12-01: 1.1.6
- Update Mobbex Embed version to 1.0.17

2020-12-01: 1.1.5
- Uniqueness improvement for reference id

2020-11-10: 1.1.4
- Update mobbex api url
- Fix Json return on embed payment

2020-10-16: 1.1.3
- Add installment filter
- Support recoverable status
- Set checkout lifetime to 5 minutes
- Fixes

2020-09-30: 1.1.0
- Introducing Mobbex Embed option
- Save and show Mobbex payment data
- Send customer and shipping to Mobbex checkout

2020-04-15: 1.0.0-RC1
- First usable version of the Module
- Customizable redirection enabled
- Webhook Controller
- Return Controller
- Info Block
- Some stuff for future use

2020-04-17: 1.0.0-RC2
- Fix folder name
- Adding default config settings
- Adding missing options on Checkout
- Adding Plugin Version.
- Adding theme settings

2020-04-23 1.0.0-RC3
- Several fixes
- Improved State/Status flow
- Invoice handling
- Fix Support for Magento 2.1, 2.2

2020-06-16 1.0.0
- Several improvements made by Improntus
- Fixes.
- Moving to stable version

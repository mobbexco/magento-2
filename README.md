# Magento 2 Mobbex Payment Gateway

## Installation

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

### Using Composer: 
1. Run the following commands in the Magento installation folder
    ```
    composer require mobbexco/magento-2
    php bin/magento setup:upgrade
    php bin/magento setup:static-content:deploy -f
    ```

## Changelog:

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

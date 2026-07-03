# Zru for PrestaShop

Official PrestaShop payment module for the [Zru](https://www.zrupay.com) API. It lets your shop accept payments through the multiple payment gateways connected to your Zru account, with a single integration.

## Requirements

- PrestaShop 1.7.6 to 9.x (tested on PrestaShop 9.1 / PHP 8.4)
- PHP 7.1 or later, with the `curl` and `json` extensions
- A [Zru account](https://www.zrupay.com) with an environment (public key + secret key)
- A publicly reachable shop URL, so Zru can deliver payment notifications

## Installation

### From the back office (recommended)

1. Download the module zip.
2. In your shop's back office go to **Modules > Module Manager > Upload a module** and select the zip.
3. Once installed, click **Configure**.

### Manual

1. Extract the zip and upload the `zru` folder to the `modules/` directory of your shop (FTP/SFTP).
2. In the back office go to **Modules > Module Manager**, search for "Zru" and click **Install**.

## Configuration

In **Modules > Module Manager > Zru > Configure**:

| Field | Description |
|---|---|
| Key | Public key of your Zru environment |
| Secret Key | Secret key of your Zru environment |
| Title | Name of the payment option shown on the checkout page |
| Description | Text shown with the payment option on the checkout page |
| Image | URL of an image shown with the payment option (optional) |
| IFrame | Show the Zru payment page embedded in the shop instead of redirecting |

You can find the credentials of your environment in your Zru panel, under the environment settings.

The payment option only appears on the checkout when the module is fully configured **and** the customer's currency, country and group are allowed for it. You can manage those restrictions in **Payment > Preferences**.

## How it works

1. On the payment step the customer selects the Zru payment option.
2. The module creates a transaction in Zru with the full cart detail (every product with its quantity and price, gift wrapping, shipping and discounts) and redirects the customer to the Zru payment page (or shows it in an iframe).
3. When the payment is completed, Zru notifies the shop server-to-server. The module verifies the notification signature, reads the transaction back from the Zru API and creates the order as **Payment accepted**. Repeated notifications are handled idempotently.

## Support

- Documentation: [docs.zrupay.com](https://docs.zrupay.com)
- Email: [hola@zrupay.com](mailto:hola@zrupay.com)

## License

Open Software License (OSL 3.0). See the license header in each source file.

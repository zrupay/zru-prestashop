<?php
/**
 * zru Module
 *
 * Copyright (c) 2026 Zru
 *
 * @category  Payment
 * @author    Zru, <www.zrupay.com>
 * @copyright 2026, Zru
 * @link      https://www.zrupay.com/
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 * Description:
 *
 * Payment module zru
 *
 * --
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to hola@zrupay.com so we can send you a copy immediately.
 */

if (!defined('_PS_VERSION_')) {
    exit();
}

require_once dirname(__FILE__) . '/lib/ZRU/ZRUClient.php';

class Zru extends PaymentModule
{
    /**
     * Validation errors of the configuration form
     *
     * @var array
     */
    private $postErrors = array();

    /**
     * Build module
     *
     * @see PaymentModule::__construct()
     */
    public function __construct()
    {
        $this->name = 'zru';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Zru';
        $this->module_key = '26f33953dc3c6c678b10fb0314dc92b2';
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;
        $this->controllers = array(
            'payment',
            'validation',
        );
        $this->ps_versions_compliancy = array('min' => '1.7.6.0', 'max' => _PS_VERSION_);

        parent::__construct();

        $this->displayName = $this->l('Zru');
        $this->description = $this->l('Allows to receive payments from several payment gateways');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');

        /* Add configuration warnings if needed */
        if (!Configuration::get('ZRU_KEY')
            || !Configuration::get('ZRU_SECRET_KEY')
            || !Configuration::get('ZRU_TITLE')
            || !Configuration::get('ZRU_DESCRIPTION')) {
            $this->warning = $this->l('Module configuration is incomplete.');
        }
    }

    /**
     * Install module
     *
     * @see PaymentModule::install()
     */
    public function install()
    {
        return parent::install()
            && Configuration::updateValue('ZRU_KEY', '')
            && Configuration::updateValue('ZRU_SECRET_KEY', '')
            && Configuration::updateValue('ZRU_TITLE', '')
            && Configuration::updateValue('ZRU_DESCRIPTION', '')
            && Configuration::updateValue('ZRU_IFRAME', '')
            && Configuration::updateValue('ZRU_ICON', '')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayPaymentReturn');
    }

    /**
     * Uninstall module
     *
     * @see PaymentModule::uninstall()
     */
    public function uninstall()
    {
        return Configuration::deleteByName('ZRU_KEY')
            && Configuration::deleteByName('ZRU_SECRET_KEY')
            && Configuration::deleteByName('ZRU_TITLE')
            && Configuration::deleteByName('ZRU_DESCRIPTION')
            && Configuration::deleteByName('ZRU_IFRAME')
            && Configuration::deleteByName('ZRU_ICON')
            && parent::uninstall();
    }

    /**
     * Validate submited data
     */
    private function postValidation()
    {
        $this->postErrors = array();
        if (Tools::isSubmit('submitUpdate')) {
            if (!Tools::getValue('ZRU_KEY')) {
                $this->postErrors[] = $this->l('The "Key" field is required.');
            }
            if (!Tools::getValue('ZRU_SECRET_KEY')) {
                $this->postErrors[] = $this->l('The "Secret Key" field is required.');
            }
            if (!Tools::getValue('ZRU_TITLE')) {
                $this->postErrors[] = $this->l('The "Title" field is required.');
            }
            if (!Tools::getValue('ZRU_DESCRIPTION')) {
                $this->postErrors[] = $this->l('The "Description" field is required.');
            }
        }
    }

    /**
     * Update submited configurations
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitUpdate')) {
            $this->postValidation();

            if (!count($this->postErrors)) {
                Configuration::updateValue('ZRU_KEY', Tools::getValue('ZRU_KEY'));
                Configuration::updateValue('ZRU_SECRET_KEY', Tools::getValue('ZRU_SECRET_KEY'));
                Configuration::updateValue('ZRU_TITLE', Tools::getValue('ZRU_TITLE'));
                Configuration::updateValue('ZRU_DESCRIPTION', Tools::getValue('ZRU_DESCRIPTION'));
                Configuration::updateValue('ZRU_IFRAME', Tools::getValue('ZRU_IFRAME') ? 'active' : '');
                Configuration::updateValue('ZRU_ICON', Tools::getValue('ZRU_ICON'));
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            } else {
                foreach ($this->postErrors as $err) {
                    $output .= $this->displayError($err);
                }
            }
        }

        return $output . $this->renderForm();
    }

    /**
     * Build the configuration form
     *
     * @return string
     */
    private function renderForm()
    {
        $fieldsForm = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Zru settings'),
                    'icon' => 'icon-cogs',
                ),
                'description' => $this->l('To use Zru you need a customer account. Get the credentials of your application at zrupay.com.'),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Key'),
                        'name' => 'ZRU_KEY',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Secret Key'),
                        'name' => 'ZRU_SECRET_KEY',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Title'),
                        'name' => 'ZRU_TITLE',
                        'desc' => $this->l('Name of the payment option shown on the checkout page.'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Description'),
                        'name' => 'ZRU_DESCRIPTION',
                        'desc' => $this->l('Description of the payment option shown on the checkout page.'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Image'),
                        'name' => 'ZRU_ICON',
                        'desc' => $this->l('URL of an image shown with the payment option (optional).'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('IFrame'),
                        'name' => 'ZRU_IFRAME',
                        'is_bool' => true,
                        'desc' => $this->l('Show the payment page inside the shop instead of redirecting to Zru.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submitUpdate';
        $helper->fields_value = array(
            'ZRU_KEY' => Tools::getValue('ZRU_KEY', Configuration::get('ZRU_KEY')),
            'ZRU_SECRET_KEY' => Tools::getValue('ZRU_SECRET_KEY', Configuration::get('ZRU_SECRET_KEY')),
            'ZRU_TITLE' => Tools::getValue('ZRU_TITLE', Configuration::get('ZRU_TITLE')),
            'ZRU_DESCRIPTION' => Tools::getValue('ZRU_DESCRIPTION', Configuration::get('ZRU_DESCRIPTION')),
            'ZRU_ICON' => Tools::getValue('ZRU_ICON', Configuration::get('ZRU_ICON')),
            'ZRU_IFRAME' => Tools::getValue('ZRU_IFRAME', Configuration::get('ZRU_IFRAME') == 'active' ? 1 : 0),
        );

        return $helper->generateForm(array($fieldsForm));
    }

    /**
     * Build and display payment option
     *
     * @param array $params
     * @return \PrestaShop\PrestaShop\Core\Payment\PaymentOption[]
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->isPayment() || !$this->checkCurrency($params['cart'])) {
            return array();
        }

        $this->context->smarty->assign('path', $this->_path);
        $this->context->smarty->assign('title', Configuration::get('ZRU_TITLE'));
        $this->context->smarty->assign('description', Configuration::get('ZRU_DESCRIPTION'));
        $this->context->smarty->assign('icon', Configuration::get('ZRU_ICON'));

        $paymentOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $paymentOption->setModuleName($this->name)
            ->setCallToActionText(Configuration::get('ZRU_TITLE'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(
                'token' => Tools::getToken(false),
            ), true))
            ->setAdditionalInformation($this->context->smarty->fetch(
                'module:zru/views/templates/hook/payment_options.tpl'
            ));

        return array($paymentOption);
    }

    /**
     * Build and display confirmation
     *
     * @param array $params
     * @return string Templatepart
     */
    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->isPayment() || !isset($params['order'])) {
            return '';
        }

        $this->context->smarty->assign(array(
            'path' => $this->_path,
            'shop_name' => $this->context->shop->name,
            'amount' => $this->context->getCurrentLocale()->formatPrice(
                $params['order']->getOrdersTotalPaid(),
                (new Currency($params['order']->id_currency))->iso_code
            ),
        ));

        return $this->display(__FILE__, 'views/templates/hook/payment_return.tpl');
    }

    /**
     * Check if the cart currency is allowed for this module
     *
     * @param Cart $cart
     * @return bool
     */
    public function checkCurrency($cart)
    {
        $currencyOrder = new Currency($cart->id_currency);
        $currenciesModule = $this->getCurrency($cart->id_currency);

        /* Radio/default modes return a single Currency object */
        if ($currenciesModule instanceof Currency) {
            return (int) $currencyOrder->id === (int) $currenciesModule->id;
        }

        if (is_array($currenciesModule)) {
            foreach ($currenciesModule as $currencyModule) {
                if ($currencyOrder->id == $currencyModule['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if payment is active
     *
     * @return bool
     */
    public function isPayment()
    {
        if (!$this->active) {
            return false;
        }

        if (!Configuration::get('ZRU_KEY')
            || !Configuration::get('ZRU_SECRET_KEY')
            || !Configuration::get('ZRU_TITLE')
            || !Configuration::get('ZRU_DESCRIPTION')) {
            return false;
        }

        return true;
    }
}

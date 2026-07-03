{**
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
 *}

<section id="zru-payment-options">
    {if $icon}
    <img src="{$icon|escape:'htmlall'}"/>
    <br>
    {/if}
    <p>{$description|escape:'htmlall'}</p>
</section>
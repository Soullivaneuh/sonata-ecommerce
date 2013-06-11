<?php

/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Sonata\Component\Basket;

use Sonata\Component\Payment\PaymentInterface;
use Sonata\Component\Delivery\DeliveryInterface;
use Sonata\Component\Customer\AddressInterface;
use Sonata\Component\Product\ProductInterface;
use Sonata\Component\Basket\BasketInterface;
use Sonata\Component\Customer\CustomerInterface;
use Sonata\Component\Product\Pool;

class Basket implements \Serializable, BasketInterface
{
    protected $basketElements;

    protected $positions = array();

    protected $cptElement = 0;

    protected $inBuild = false;

    protected $productPool;

    protected $paymentAddress;

    protected $paymentMethod;

    protected $paymentMethodCode;

    protected $paymentAddressId;

    protected $deliveryAddress;

    protected $deliveryMethod;

    protected $deliveryAddressId;

    protected $deliveryMethodCode;

    protected $customer;

    protected $customerId;

    protected $options = array();

    protected $locale;

    protected $currency;

    public function __construct()
    {
        $this->basketElements = array();
    }

    /**
     * {@inheritdoc}
     */
    public function setProductPool(Pool $pool)
    {
        $this->productPool = $pool;
    }

    /**
     * {@inheritdoc}
     */
    public function getProductPool()
    {
        return $this->productPool;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return count($this->getBasketElements()) == 0;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid($elementsOnly = false)
    {
        if ($this->isEmpty()) {
            return false;
        }

        foreach ($this->getBasketElements() as $element) {
            if ($element->isValid() === false) {
                return false;
            }
        }

        if ($elementsOnly) {
            return true;
        }

        if (!$this->getPaymentAddress() instanceof AddressInterface) {
            return false;
        }

        if (!$this->getPaymentMethod() instanceof PaymentInterface) {
            return false;
        }

        if (!$this->getDeliveryMethod() instanceof DeliveryInterface) {
            return false;
        }

        if (!$this->getDeliveryAddress() instanceof AddressInterface) {
            if ($this->getDeliveryMethod()->isAddressRequired()) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setDeliveryMethod(DeliveryInterface $method = null)
    {
        $this->deliveryMethod = $method;
        $this->deliveryMethodCode = $method ? $method->getCode() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getDeliveryMethod()
    {
        return $this->deliveryMethod;
    }

    /**
     * {@inheritdoc}
     */
    public function setDeliveryAddress(AddressInterface $address = null)
    {
        $this->deliveryAddress = $address;
        $this->deliveryAddressId = $address ? $address->getId() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getDeliveryAddress()
    {
        return $this->deliveryAddress;
    }

    /**
     * {@inheritdoc}
     */
    public function setPaymentMethod(PaymentInterface $method = null)
    {
        $this->paymentMethod = $method;
        $this->paymentMethodCode = $method ? $method->getCode() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * {@inheritdoc}
     */
    public function setPaymentAddress(AddressInterface $address = null)
    {
        $this->paymentAddress = $address;
        $this->paymentAddressId = $address ? $address->getId() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentAddress()
    {
        return $this->paymentAddress;
    }

    /**
     * {@inheritdoc}
     */
    public function isAddable(ProductInterface $product)
    {
        /*
        * We ask the product repository if it can be added to the basket
        */
        $isAddableBehavior = call_user_func_array(
            array($this->getProductPool()->getProvider($product), 'isAddableToBasket'),
            array_merge(array($this), func_get_args())
        );

        return $isAddableBehavior;
    }

    /**
     * {@inheritdoc}
     */
    public function reset($full = true)
    {
        $this->deliveryAddressId = null;
        $this->deliveryAddress = null;
        $this->deliveryMethod = null;
        $this->deliveryMethodCode = null;

        $this->paymentAddressId = null;
        $this->paymentAddress = null;
        $this->paymentMethod = null;
        $this->paymentMethodCode = null;

        if ($full) {
            $this->basketElements = array();
            $this->positions = array();
            $this->cptElement = 0;
            $this->customerId = null;
            $this->customer = null;
            $this->options = array();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBasketElements()
    {
        return $this->basketElements;
    }

    /**
     * {@inheritdoc}
     */
    public function setBasketElements($basketElements)
    {
        $this->basketElements = $basketElements;
    }

    /**
     * {@inheritdoc}
     */
    public function countBasketElements()
    {
        return count($this->basketElements);
    }

    /**
     * {@inheritdoc}
     */
    public function hasBasketElements()
    {
        return $this->countBasketElements() > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getElement(ProductInterface $product)
    {
        if (!$this->hasProduct($product)) {
            throw new \RuntimeException('The product does not exist');
        }

        $pos = $this->positions[$product->getId()];

        return $this->getElementByPos($pos);
    }

    /**
     * {@inheritdoc}
     */
    public function getElementByPos($pos)
    {
        return isset($this->basketElements[$pos]) ? $this->basketElements[$pos] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function removeElement(BasketElementInterface $element)
    {
        return $this->removeBasketElement($element);
    }

    /**
     * {@inheritdoc}
     */
    public function addBasketElement(BasketElementInterface $basketElement)
    {
        $basketElement->setPosition($this->cptElement);

        $this->basketElements[$this->cptElement] = $basketElement;
        $this->positions[$basketElement->getProduct()->getId()] = $this->cptElement;

        $this->cptElement++;

        $this->buildPrices();
    }

    /**
     * {@inheritdoc}
     */
    public function removeBasketElement(BasketElementInterface $basketElement)
    {
        $pos = $element->getPosition();

        $this->cptElement--;

        unset(
            $this->positions[$element->getProduct()->getId()],
            $this->basketElements[$pos]
        );

        if (!$this->inBuild) {
            $this->buildPrices();
        }

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public function hasRecurrentPayment()
    {
        foreach ($this->getBasketElements() as $basketElement) {
            $product = $basketElement->getProduct();

            if ($product instanceof ProductInterface) {
                if ($product->isRecurrentPayment() === true) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getTotal($vat = false, $recurrentOnly = null)
    {
        $total = 0;

        foreach ($this->getBasketElements() as $basketElement) {
            $product = $basketElement->getProduct();

            if ($recurrentOnly === true && $product->isRecurrentPayment() === false) {
                continue;
            }

            if ($recurrentOnly === false && $product->isRecurrentPayment() === true) {
                continue;
            }

            $total += $basketElement->getTotal($vat);
        }

        $total += $this->getDeliveryPrice($vat);

        return bcadd($total, 0, 2);
    }

    /**
     * {@inheritdoc}
     */
    public function getVatAmount()
    {
        $vat = 0;

        foreach ($this->getBasketElements() as $basketElement) {
            $vat += $basketElement->getVatAmount();
        }

        $deliveryMethod = $this->getDeliveryMethod();

        if ($deliveryMethod instanceof DeliveryInterface) {
            $vat += $deliveryMethod->getVatAmount($this);
        }

        return $vat;
    }

    /**
     * {@inheritdoc}
     */
    public function getDeliveryPrice($vat = false)
    {
        $method = $this->getDeliveryMethod();

        if (!$method instanceof DeliveryInterface) {
            return 0;
        }

        return $method->getTotal($this, $vat);
    }

    /**
     * {@inheritdoc}
     */
    public function hasProduct(ProductInterface $product)
    {
        if (!array_key_exists($product->getId(), $this->positions)) {
            return false;
        }

        $pos = $this->positions[$product->getId()];

        foreach ($this->getBasketElements() as $basketElement) {
            if ($pos == $basketElement->getPosition()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function buildPrices()
    {
        $this->inBuild = true;

        foreach ($this->getBasketElements() as $basketElement) {
            $product = $basketElement->getProduct();

            if (!is_object($product)) {
                $this->removeElement($basketElement);

                continue;
            }

            $provider = $this->getProductPool()->getProvider($product);
            $price    = $provider->basketCalculatePrice($this, $basketElement);
            $basketElement->setPrice($price);
        }

        $this->inBuild = false;
    }

    /**
     * {@inheritdoc}
     */
    public function clean()
    {
        foreach ($this->getBasketElements() as $basketElement) {
            if ($basketElement->getDelete()) {
                $this->removeElement($basketElement);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array(
            'basketElements'        => $this->getBasketElements(),
            'positions'             => $this->positions,
            'deliveryAddressId'     => $this->deliveryAddressId,
            'paymentAddressId'      => $this->paymentAddressId,
            'paymentMethodCode'     => $this->paymentMethodCode,
            'cptElement'            => $this->cptElement,
            'deliveryMethodCode'    => $this->deliveryMethodCode,
            'customerId'            => $this->customerId,
            'options'               => $this->options,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($data)
    {
        $data = unserialize($data);

        $properties = array(
            'basketElements',
            'positions',
            'deliveryAddressId',
            'deliveryMethodCode',
            'paymentAddressId',
            'paymentMethodCode',
            'cptElement',
            'customerId',
            'options',
        );

        foreach ($properties as $property) {
            $this->$property = isset($data[$property]) ? $data[$property] : $this->$property;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDeliveryAddressId($deliveryAddressId)
    {
        $this->deliveryAddressId = $deliveryAddressId;
    }

    /**
     * {@inheritdoc}
     */
    public function getDeliveryAddressId()
    {
        return $this->deliveryAddressId;
    }

    /**
     * {@inheritdoc}
     */
    public function setPaymentAddressId($paymentAddressId)
    {
        $this->paymentAddressId = $paymentAddressId;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentAddressId()
    {
        return $this->paymentAddressId;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethodCode()
    {
        return $this->paymentMethodCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getDeliveryMethodCode()
    {
        return $this->deliveryMethodCode;
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomer(CustomerInterface $customer = null)
    {
        $this->customer = $customer;
        $this->customerId = $customer ? $customer->getId() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerId($customerId)
    {
        $this->customerId = $customerId;
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomerId()
    {
        return $this->customerId;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($name, $default = null)
    {
        if (!array_key_exists($name, $this->options)) {
            return $default;
        }

        return $this->options[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrency()
    {
        return $this->currency;
    }
}
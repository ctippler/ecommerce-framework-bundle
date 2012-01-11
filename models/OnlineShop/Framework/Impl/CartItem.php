<?php

class OnlineShop_Framework_Impl_CartItem extends Pimcore_Model_Abstract implements OnlineShop_Framework_ICartItem {

    /**
     * @var OnlineShop_Framework_AbstractProduct
     */
    protected $product;
    protected $productId;
    protected $itemKey;
    protected $count;
    protected $parentItemKey = "";

    protected $subItems = null;

    /**
     * @var OnlineShop_Framework_ICart
     */
    protected $cart;
    protected $cartId;

    public function setCount($count) {
        $this->count = $count;
    }

    public function getCount() {
        return $this->count;
    }

    public function setProduct(OnlineShop_Framework_AbstractProduct $product) {
        $this->product = $product;
    }

    /**
     * @return OnlineShop_Framework_AbstractProduct
     */
    public function getProduct() {
        if ($this->product) {
            return $this->product;
        }
        $this->product = OnlineShop_Framework_AbstractProduct::getById($this->productId);
        return $this->product;
    }

    public function setCart(OnlineShop_Framework_ICart $cart) {
        $this->cart = $cart;
        $this->cartId = $cart->getId();
    }

    public function getCart() {
        if (empty($this->cart)) {
            $this->cart = OnlineShop_Framework_Impl_Cart::getById($this->cartId);
        }
        return $this->cart;
    }

    public function getCartId() {
        return $this->cartId;
    }

    public function setCartId($cartId) {
        $this->cartId = $cartId;
    }

    public function getProductId() {
        if ($this->productId) {
            return $this->productId;
        }
        return $this->getProduct()->getId();
    }

    public function setProductId($productId) {
        $this->productId = $productId;
    }

    public function setParentItemKey($parentItemKey) {
        $this->parentItemKey = $parentItemKey;
    }

    public function getParentItemKey() {
        return $this->parentItemKey;
    }

    public function setItemKey($itemKey) {
        $this->itemKey = $itemKey;
    }

    public function getItemKey() {
        return $this->itemKey;
    }


    public function save() {
        $items = $this->getSubItems();
        if (!empty($this->subItems)) {
            foreach ($this->subItems as $item) {
                $item->save();
            }
        }
        $this->getResource()->save();
    }

    public static function getByCartIdItemKey($cartId, $itemKey, $parentKey = "") {
        $cacheKey = OnlineShop_Framework_Impl_CartItem_Resource::TABLE_NAME . "_" . $cartId . "_" . $parentKey . $itemKey;

        try {
            $cartItem = Zend_Registry::get($cacheKey);
        }
        catch (Exception $e) {
            try {
                $cartItem = new self();
                $cartItem->getResource()->getByCartIdItemKey($cartId, $itemKey, $parentKey);
                $cartItem->getSubItems();
                Zend_Registry::set($cacheKey, $cartItem);
            } catch (Exception $ex) {
                Logger::debug($ex->getMessage());
                return null;
            }

        }

        return $cartItem;
    }

    public static function removeAllFromCart($cartId) {
        $cartItem = new self();
        $cartItem->getResource()->removeAllFromCart($cartId);
    }

    /**
     * @return OnlineShop_Framework_ICartItem[]
     */
    public function getSubItems() {

        if ($this->subItems == null) {
            $this->subItems = array();

            $itemList = new OnlineShop_Framework_Impl_CartItem_List();
            $db = Pimcore_Resource::get();
            $itemList->setCondition("cartId = " . $db->quote($this->getCartId()) . " AND parentItemKey = " . $db->quote($this->getItemKey()));

            foreach ($itemList->getCartItems() as $item) {
                if ($item->getProduct() != null) {
                    $this->subItems[] = $item;
                } else {
                    Logger::warn("product " . $item->getProductId() . " not found");
                }
            }
        }
        return $this->subItems;
    }

    /**
     * @param  OnlineShop_Framework_ICartItem[] $subItems
     * @return void
     */
    public function setSubItems($subItems) {
        foreach ($subItems as $item) {
            $item->setParentItemKey($this->getItemKey());
        }
        $this->subItems = $subItems;
    }


    /**
     * @return OnlineShop_Framework_IPrice
     */
    public function getPrice() {
        if ($this->getProduct() instanceof OnlineShop_Framework_AbstractSetProduct) {
            return $this->getProduct()->getOSPrice($this->getCount(),$this->getSetEntries());

        } else {
            return $this->getProduct()->getOSPrice($this->getCount());
        }
    }



    /**
     * @return stdClass
     */
    public function getPriceInfo() {
        if ($this->getProduct() instanceof OnlineShop_Framework_AbstractSetProduct) {
            return $this->getProduct()->getOSPriceInfo($this->getCount(),$this->getSetEntries());
        } else {
            return $this->getProduct()->getOSPriceInfo($this->getCount());
        }
    }

    /**
     * @return OnlineShop_Framework_IAvailability
     */
    public function getAvailabilityInfo() {
        if ($this->getProduct() instanceof OnlineShop_Framework_AbstractSetProduct) {
            return $this->getProduct()->getOSAvailabilityInfo($this->getCount(),$this->getSetEntries());

        } else {
            return $this->getProduct()->getOSAvailabilityInfo($this->getCount());
        }
    }

     /**
     * @return OnlineShop_Framework_AbstractSetProductEntry[]
     */
    protected  function getSetEntries() {
        $products = array();
        foreach ($this->getSubItems() as $item) {
            $products[] = new OnlineShop_Framework_AbstractSetProductEntry($item->getProduct(), $item->getCount());
        }
        return $products;

    }
}

<?php

namespace Egits\WishlistGroups\Controller\Index;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Controller\ResultInterface;
use Magento\Wishlist\Model\ResourceModel\Wishlist\CollectionFactory as WishlistCollectionFactory;

/**
 * Class ListWishlist
 * @package Egits\WishlistGroups\Controller\Index
 */
class ListWishlist implements ActionInterface
{
    /**
     * @var JsonFactory
     */
    protected $jsonFactory;
    /**
     * @var Session
     */
    protected $customerSession;
    /**
     * @var WishlistCollectionFactory
     */
    protected $wishlistCollectionFactory;

    /**
     * ListWishlist constructor.
     *
     * @param JsonFactory $jsonFactory
     * @param Session $customerSession
     * @param WishlistCollectionFactory $wishlistCollectionFactory
     */
    public function __construct(
        JsonFactory $jsonFactory,
        Session $customerSession,
        WishlistCollectionFactory $wishlistCollectionFactory
    )
    {
        $this->jsonFactory = $jsonFactory;
        $this->customerSession = $customerSession;
        $this->wishlistCollectionFactory = $wishlistCollectionFactory;
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData([
                'success' => false,
                'message' => __('You must be logged in to view wishlists.')
            ]);
        }

        try {
            $customerId = $this->customerSession->getCustomerId();
            $wishlistCollection = $this->wishlistCollectionFactory->create()
                ->addFieldToFilter('customer_id', $customerId);

            $wishlists = [];
            foreach ($wishlistCollection as $wishlist) {
                $wishlists[] = [
                    'id' => $wishlist->getId(),
                    'name' => $wishlist->getData('name') ?? __('Unnamed Wishlist'),
                ];
            }

            return $result->setData([
                'success' => true,
                'wishlists' => $wishlists
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

<?php

namespace Egits\WishlistGroups\Controller\Index;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Controller\ResultInterface;
use Magento\Wishlist\Model\WishlistFactory;
use Magento\Wishlist\Model\ResourceModel\WishlistFactory as ResourceFactory;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\RequestInterface;
use Magento\Wishlist\Model\ResourceModel\Wishlist\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Class Add
 * @package Egits\WishlistGroups\Controller\Index
 */
class Add implements ActionInterface
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
     * @var WishlistFactory
     */
    protected $wishlistFactory;
    /**
     * @var ProductRepository
     */
    protected $productRepository;
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var ResourceFactory
     */
    protected $resourceFactory;
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    public function __construct(
        JsonFactory $jsonFactory,
        Session $customerSession,
        WishlistFactory $wishlistFactory,
        ProductRepository $productRepository,
        RequestInterface $request,
        LoggerInterface $logger,
        ResourceFactory $resourceFactory,
        CollectionFactory $collectionFactory,
        ScopeConfigInterface $scopeConfig,
        ManagerInterface $messageManager

    )
    {
        $this->jsonFactory = $jsonFactory;
        $this->customerSession = $customerSession;
        $this->wishlistFactory = $wishlistFactory;
        $this->productRepository = $productRepository;
        $this->request = $request;
        $this->logger = $logger;
        $this->resourceFactory = $resourceFactory;
        $this->collectionFactory = $collectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->messageManager = $messageManager;
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
                'message' => __('You must be logged in to add items to the wishlist.')
            ]);
        }

        try {
            $customerId = $this->customerSession->getCustomerId();

            // Fetch request parameters
            $wishlistName = $this->request->getParam('name');
            $productId = $this->request->getParam('product');

            // Log incoming data for debugging
            $this->logger->info("Received Data: Wishlist Name - " . $wishlistName . ", Product ID - " . $productId);

            if (!$wishlistName) {
                throw new LocalizedException(__('Wishlist name is required.'));
            }
            if (!$productId) {
                throw new LocalizedException(__('Product ID is required.'));
            }
            // Get the wishlist count

            $wishlistCollection = $this->collectionFactory->create()
                ->addFieldToFilter('customer_id', $customerId);
            $wishlistCountInDb = $wishlistCollection->getSize();
            $wishlistCount = $this->scopeConfig->getValue(
                'wishlist_groups/wishlist/wishlist_count',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
             );
            if ($wishlistCountInDb < $wishlistCount) {
                // Load wishlist or create a new one
                $wishlist = $this->wishlistFactory->create();

                if (!$wishlist->getId()) {
                    $wishlist->setCustomerId($customerId)
                        ->setName($wishlistName) // Ensure name is set
                        ->setVisibility(1)
                        ->save();
                }


                // Load product
                $product = $this->productRepository->getById($productId);
                if (!$product->getId()) {
                    throw new LocalizedException(__('Invalid product.'));
                }

                // Add product to wishlist
                $wishlist->addNewItem($product);
                $this->resourceFactory->create()->save($wishlist);
                $this->messageManager->addSuccessMessage("Wishlist Created & Product Added Successfully.");
                return $result->setData([
                    'success' => true,
                    'message' => __('Wishlist Created & Product Added Successfully.')
                ]);
            } else {
                $this->messageManager->addErrorMessage("You already created " . $wishlistCount . " wishlists. If you want to create a new one, please delete an existing one and try again.");
                return $result->setData([
                    'success' => false,
                    'message' => __("You already created " . $wishlistCount . " wishlists. If you want to create a new one, please delete an existing one and try again.")
                ]);

            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage("Unable to add the product to wishlist right now. Please try again");
            $this->logger->error("Wishlist Error: " . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

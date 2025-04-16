<?php

namespace Egits\WishlistGroups\Controller\Index;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Controller\ResultInterface;
use Magento\Wishlist\Model\WishlistFactory;
use Magento\Wishlist\Model\ResourceModel\WishlistFactory as ResourWishlistFactory;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Class Existing
 * @package Egits\WishlistGroups\Controller\Index
 */
class Existing implements ActionInterface
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
     * @var ResourWishlistFactory
     */
    protected $resourceFactory;
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
        ResourWishlistFactory $resourceFactory,
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
            $wishlistId = $this->request->getParam('wishlist_id');
            $productId = $this->request->getParam('product_id'); // Ensure this is product_id

            // Log incoming data for debugging
            $this->logger->info("Received Data: Wishlist ID - " . $wishlistId . ", Product ID - " . $productId);

            if (!$wishlistId) {
                throw new LocalizedException(__('Wishlist ID is required.'));
            }
            if (!$productId) {
                throw new LocalizedException(__('Product ID is required.'));
            }

            // Load product
            $product = $this->productRepository->getById($productId);
            if (!$product->getId()) {
                throw new LocalizedException(__('Invalid product.'));
            }

            // Load the wishlist by ID
            $wishlist = $this->wishlistFactory->create()->load($wishlistId);
            if (!$wishlist->getId() || $wishlist->getCustomerId() != $customerId) {
                throw new LocalizedException(__('Invalid wishlist.'));
            }

            // Add product to the specific wishlist
            $wishlist->addNewItem($product);
            $this->resourceFactory->create()->save($wishlist);
            $this->messageManager->addSuccessMessage("Product added to the selected wishlist successfully.");
            return $result->setData([
                'success' => true,
                'message' => __('Product added to the selected wishlist successfully.')
            ]);
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

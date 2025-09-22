<?php
//
namespace FishPig\CategoryProductUrlRedirects\Plugin\Magento\CatalogUrlRewrite\Model\Storage;

use Magento\CatalogUrlRewrite\Plugin\DynamicCategoryRewrites;
use Magento\CatalogUrlRewrite\Model\Storage\DynamicStorage;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

class DynamicStoragePlugin
{
    /**
     * 
     */
    public function __construct(
        private \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        private \Magento\Framework\App\ResourceConnection $resourceConnection,
        private \Magento\UrlRewrite\Service\V1\Data\UrlRewriteFactory $urlRewriteFactory,
        private \Magento\Framework\Api\DataObjectHelper $dataObjectHelper
    ) {}

    /**
     * 
     */
    public function aroundFindOneByData(
        DynamicStorage $subject,
        callable $proceed,
        array $data
    ): ?UrlRewrite {
        if (($result = $proceed($data)) !== null) {
            return $result;
        } elseif (!isset($data['request_path']) || strpos($data['request_path'], '/') === false) {
            return null;
        }

        $urlParts = explode('/', $data['request_path']);

        $productRequestPath = array_pop($urlParts);
        $categoryRequestPath = implode('/', $urlParts);

        $productData = array_merge($data, ['request_path' => $productRequestPath]);
        $categoryData = array_merge($data, ['request_path' => $categoryRequestPath . $this->getCategoryUrlSuffix()]);

        if (!($productUrlRewriteData = $this->findOneByData($productData)) || !$this->findOneByData($categoryData)) {
            return null;
        }

        $finalRewrite = [
            'entity_type' => 'custom',
            'redirect_type' => 301,
            'request_path' => $data['request_path'],
            'target_path' => $productUrlRewriteData['request_path'],
            'store_id' => $data['store_id']

        ];

        $dataObject = $this->urlRewriteFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $dataObject,
            $finalRewrite,
            \Magento\UrlRewrite\Service\V1\Data\UrlRewrite::class
        );
        return $dataObject;
    }

    /**
     * 
     */
    private function findOneByData(array $data): ?array
    {
        $db = $this->resourceConnection->getConnection();

        $select = $db->select()->from(
            $this->resourceConnection->getTableName('url_rewrite'),
            '*'
        )->limit(1);

        foreach ($data as $key => $value) {
            $select->where($key . '=?', $value);
        }

        return $db->fetchRow($select) ?: null;
    }

    /**
     * 
     */
    private function getCategoryUrlSuffix(): string
    {
        $urlSuffix = $this->scopeConfig->getValue(
            \Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator::XML_PATH_CATEGORY_URL_SUFFIX
        ) ?? '';

        if ($urlSuffix === './') {
            return '';
        }

        return trim($urlSuffix, '/');
    }
}
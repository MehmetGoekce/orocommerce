<?php

namespace Oro\Bundle\ProductBundle\EventListener;

use Doctrine\Common\Persistence\ManagerRegistry;

use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Manager\AttachmentManager;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Entity\Repository\AttributeFamilyRepository;
use Oro\Bundle\EntityConfigBundle\Manager\AttributeManager;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\Repository\ProductRepository;
use Oro\Bundle\ProductBundle\Entity\Repository\ProductUnitRepository;
use Oro\Bundle\ProductBundle\Search\WebsiteSearchProductIndexDataProvider;
use Oro\Bundle\WebsiteBundle\Provider\AbstractWebsiteLocalizationProvider;
use Oro\Bundle\WebsiteBundle\Provider\WebsiteLocalizationProvider;
use Oro\Bundle\WebsiteSearchBundle\Event\IndexEntityEvent;
use Oro\Bundle\WebsiteSearchBundle\Manager\WebsiteContextManager;

/**
 * Add product related data to search index
 * Main data added from product attributes and some data added manually inside listener
 */
class WebsiteSearchProductIndexerListener
{
    /** @var WebsiteContextManager */
    private $websiteContextManager;

    /** @var WebsiteLocalizationProvider */
    private $websiteLocalizationProvider;

    /** @var ManagerRegistry */
    private $registry;

    /** @var AttachmentManager */
    private $attachmentManager;

    /** @var AttributeManager */
    private $attributeManager;

    /** @var WebsiteSearchProductIndexDataProvider */
    private $dataProvider;

    /**
     * @param AbstractWebsiteLocalizationProvider   $websiteLocalizationProvider
     * @param WebsiteContextManager                 $websiteContextManager
     * @param ManagerRegistry                       $registry
     * @param AttachmentManager                     $attachmentManager
     * @param AttributeManager                      $attributeManager
     * @param WebsiteSearchProductIndexDataProvider $dataProvider
     */
    public function __construct(
        AbstractWebsiteLocalizationProvider $websiteLocalizationProvider,
        WebsiteContextManager $websiteContextManager,
        ManagerRegistry $registry,
        AttachmentManager $attachmentManager,
        AttributeManager $attributeManager,
        WebsiteSearchProductIndexDataProvider $dataProvider
    ) {
        $this->websiteLocalizationProvider = $websiteLocalizationProvider;
        $this->websiteContextManager = $websiteContextManager;
        $this->registry = $registry;
        $this->attachmentManager = $attachmentManager;
        $this->attributeManager = $attributeManager;
        $this->dataProvider = $dataProvider;
    }

    /**
     * @param IndexEntityEvent $event
     */
    public function onWebsiteSearchIndex(IndexEntityEvent $event)
    {
        $websiteId = $this->websiteContextManager->getWebsiteId($event->getContext());
        if (!$websiteId) {
            $event->stopPropagation();

            return;
        }

        /** @var Product[] $products */
        $products = $event->getEntities();

        $productIds = array_map(
            function (Product $product) {
                return $product->getId();
            },
            $products
        );

        $localizations = $this->websiteLocalizationProvider->getLocalizationsByWebsiteId($websiteId);
        $productImages = $this->getProductRepository()->getListingImagesFilesByProductIds($productIds);
        $productUnits = $this->getProductUnitRepository()->getProductsUnits($productIds);
        $attributes = $this->attributeManager->getAttributesByClass(Product::class);
        $attributeFamilies = $this->getAttributeFamilyRepository()->getFamilyIdsForAttributes($attributes);

        foreach ($products as $product) {
            $productId = $product->getId();

            foreach ($attributes as $attribute) {
                if (!$this->isAllowedToIndex($attribute, $product, $attributeFamilies)) {
                    continue;
                }

                $data = $this->dataProvider->getIndexData($product, $attribute, $localizations);

                foreach ($data as $content) {
                    $values = $this->toArray($content->getValue());

                    foreach ($values as $value) {
                        $value = $this->cleanUpString($value);

                        if ($content->isLocalized()) {
                            $event->addPlaceholderField(
                                $productId,
                                $content->getFieldName(),
                                $value,
                                $content->getPlaceholders(),
                                $content->isSearchable()
                            );
                        } else {
                            $event->addField($productId, $content->getFieldName(), $value, $content->isSearchable());
                        }
                    }
                }
            }

            $event->addField($product->getId(), 'product_id', $product->getId());
            $event->addField($product->getId(), 'sku_uppercase', strtoupper($product->getSku()), true);
            $event->addField($product->getId(), 'status', $product->getStatus());
            $event->addField($product->getId(), 'type', $product->getType());

            $this->processImages($event, $productImages, $product->getId());

            if (array_key_exists($product->getId(), $productUnits)) {
                $units = serialize($productUnits[$product->getId()]);
                $event->addField(
                    $product->getId(),
                    'product_units',
                    $units
                );
            }
        }
    }

    /**
     * @param FieldConfigModel $attribute
     * @param Product $product
     * @param array $attributeFamilies
     * @return bool
     */
    private function isAllowedToIndex(FieldConfigModel $attribute, Product $product, array $attributeFamilies)
    {
        if ($this->attributeManager->isSystem($attribute)) {
            return true;
        }

        if (!isset($attributeFamilies[$attribute->getId()])) {
            return false;
        }

        $attributeFamily = $product->getAttributeFamily();

        return !$attributeFamily || $attributeFamilies[$attribute->getId()] === $attributeFamily->getId();
    }

    /**
     * @param mixed $value
     * @return array|\Traversable
     */
    private function toArray($value)
    {
        if (is_array($value) || $value instanceof \Traversable) {
            return $value;
        }

        if (is_object($value)) {
            return [$value];
        }

        return (array)$value;
    }

    /**
     * @param IndexEntityEvent $event
     * @param array $productImages
     * @param int $productId
     */
    private function processImages(IndexEntityEvent $event, array $productImages, $productId)
    {
        if (isset($productImages[$productId])) {
            /** @var File $entity */
            $entity = $productImages[$productId];
            $largeImageUrl = $this->attachmentManager->getFilteredImageUrl(
                $entity,
                FrontendProductDatagridListener::PRODUCT_IMAGE_FILTER_LARGE
            );
            $mediumImageUrl = $this->attachmentManager->getFilteredImageUrl(
                $entity,
                FrontendProductDatagridListener::PRODUCT_IMAGE_FILTER_MEDIUM
            );
            $event->addField(
                $productId,
                'image_' . FrontendProductDatagridListener::PRODUCT_IMAGE_FILTER_LARGE,
                $largeImageUrl
            );
            $event->addField(
                $productId,
                'image_' . FrontendProductDatagridListener::PRODUCT_IMAGE_FILTER_MEDIUM,
                $mediumImageUrl
            );
        }
    }

    /**
     * Cleans up a unicode string from control characters
     *
     * @param string $string
     * @return string
     */
    private function cleanUpString($string)
    {
        return is_string($string) ? preg_replace('/[[:cntrl:]]/', '', $string) : $string;
    }

    /**
     * @return ProductRepository
     */
    protected function getProductRepository()
    {
        return $this->registry
            ->getManagerForClass('OroProductBundle:Product')
            ->getRepository('OroProductBundle:Product');
    }

    /**
     * @return ProductUnitRepository
     */
    protected function getProductUnitRepository()
    {
        return $this->registry
            ->getManagerForClass('OroProductBundle:ProductUnit')
            ->getRepository('OroProductBundle:ProductUnit');
    }

    /**
     * @return AttributeFamilyRepository
     */
    protected function getAttributeFamilyRepository()
    {
        return $this->registry
            ->getManagerForClass(AttributeFamily::class)
            ->getRepository(AttributeFamily::class);
    }
}

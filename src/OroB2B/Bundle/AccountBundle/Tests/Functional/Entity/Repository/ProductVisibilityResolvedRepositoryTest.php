<?php

namespace OroB2B\Bundle\AccountBundle\Tests\Functional\Entity\Repository;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Query;

use Oro\Bundle\EntityBundle\ORM\InsertFromSelectQueryExecutor;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

use OroB2B\Bundle\AccountBundle\Entity\Repository\ProductVisibilityResolvedRepository;
use OroB2B\Bundle\AccountBundle\Entity\Visibility\ProductVisibility;
use OroB2B\Bundle\AccountBundle\Entity\VisibilityResolved\BaseProductVisibilityResolved;
use OroB2B\Bundle\AccountBundle\Entity\VisibilityResolved\ProductVisibilityResolved;
use OroB2B\Bundle\CatalogBundle\Entity\Category;
use OroB2B\Bundle\CatalogBundle\Entity\Repository\CategoryRepository;
use OroB2B\Bundle\ProductBundle\Entity\Product;
use OroB2B\Bundle\ProductBundle\Entity\Repository\ProductRepository;
use OroB2B\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductData;
use OroB2B\Bundle\WebsiteBundle\Entity\Website;
use OroB2B\Bundle\WebsiteBundle\Tests\Functional\DataFixtures\LoadWebsiteData;

/**
 * @dbIsolation
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
class ProductVisibilityResolvedRepositoryTest extends WebTestCase
{
    /**
     * @var ProductVisibilityResolvedRepository
     */
    protected $repository;

    /**
     * @var Website
     */
    protected $website;

    protected function setUp()
    {
        $this->initClient();
        $this->website = $this->getWebsites()[0];

        $this->repository = $this->getResolvedVisibilityManager()
            ->getRepository('OroB2BAccountBundle:VisibilityResolved\ProductVisibilityResolved');

        $this->loadFixtures(['OroB2B\Bundle\AccountBundle\Tests\Functional\DataFixtures\LoadProductVisibilityData']);
    }

    public function testFindByPrimaryKey()
    {
        /** @var ProductVisibilityResolved $actualEntity */
        $actualEntity = $this->repository->findOneBy([]);
        if (!$actualEntity) {
            $this->markTestSkipped('Can\'t test method because fixture was not loaded.');
        }

        $expectedEntity = $this->repository->findByPrimaryKey(
            $actualEntity->getProduct(),
            $actualEntity->getWebsite()
        );

        $this->assertEquals(spl_object_hash($expectedEntity), spl_object_hash($actualEntity));
    }

    public function testClearTable()
    {
        $this->repository->clearTable();
        $actual = $this->repository->findAll();

        $this->assertEmpty($actual);
    }

    /**
     * @depends testClearTable
     */
    public function testInsertFromBaseTable()
    {
        $this->repository->insertFromBaseTable($this->getInsertFromSelectExecutor());
        $actual = $this->getActualArray();

        $this->assertCount(4, $actual);
        $this->assertInsertedFromBaseTable($actual);
    }

    /**
     * @depends testInsertFromBaseTable
     */
    public function testInsertByCategory()
    {
        $this->repository->insertByCategory(
            $this->getInsertFromSelectExecutor(),
            BaseProductVisibilityResolved::VISIBILITY_VISIBLE,
            array_map(function ($category) {
                /** @var Category $category */
                return $category->getId();
            }, $this->getCategories())
        );

        $actual = $this->getActualArray();

        $this->assertCount(20, $actual);
        $this->assertInsertedByCategory($actual);
    }

    public function testClearTableByWebsite()
    {
        $deleted = $this->repository->clearTable($this->website);
        $actual = $this->repository->findBy(['website' => $this->website]);

        $this->assertEmpty($actual);
        $this->assertSame(7, $deleted);
    }

    /**
     * @depends testClearTableByWebsite
     */
    public function testInsertFromBaseTableByWebsite()
    {
        $this->repository->insertFromBaseTable($this->getInsertFromSelectExecutor(), $this->website);
        $actual = $this->getActualArray();

        $this->assertCount(17, $actual);
        $this->assertInsertedFromBaseTable($actual);
    }

    /**
     * @depends testInsertFromBaseTableByWebsite
     */
    public function testInsertByCategoryForWebsite()
    {
        $categories = $this->getCategories();

        $this->repository->insertByCategory(
            $this->getInsertFromSelectExecutor(),
            BaseProductVisibilityResolved::VISIBILITY_VISIBLE,
            array_map(function ($category) {
                /** @var \OroB2B\Bundle\CatalogBundle\Entity\Category $category */
                return $category->getId();
            }, $categories),
            $this->website
        );

        $actual = $this->getActualArray();
        $this->assertCount(20, $actual);
        $this->assertInsertedByCategory($actual, $this->website);
    }

    public function testInsertUpdateDeleteAndHasEntity()
    {
        $product = $this->getReference(LoadProductData::PRODUCT_1);
        $website = $this->getReference(LoadWebsiteData::WEBSITE1);

        $where = ['product' => $product, 'website' => $website];
        $this->assertTrue($this->repository->hasEntity($where));

        $this->repository->deleteEntity($where);
        $this->assertFalse($this->repository->hasEntity($where));

        $insert = [
            'sourceProductVisibility' => null,
            'visibility' => BaseProductVisibilityResolved::VISIBILITY_VISIBLE,
            'source' => BaseProductVisibilityResolved::SOURCE_STATIC,
            'category' => null,
        ];
        $this->repository->insertEntity(array_merge($where, $insert));
        $this->assertTrue($this->repository->hasEntity($where));
        $this->assertEntityData(
            $where,
            BaseProductVisibilityResolved::VISIBILITY_VISIBLE,
            BaseProductVisibilityResolved::SOURCE_STATIC
        );

        $update = [
            'visibility' => BaseProductVisibilityResolved::VISIBILITY_HIDDEN,
            'source' => BaseProductVisibilityResolved::SOURCE_CATEGORY,
        ];
        $this->repository->updateEntity($update, $where);
        $this->assertTrue($this->repository->hasEntity($where));
        $this->assertEntityData(
            $where,
            BaseProductVisibilityResolved::VISIBILITY_HIDDEN,
            BaseProductVisibilityResolved::SOURCE_CATEGORY
        );
    }

    /**
     * @param array $where
     * @param int $visibility
     * @param int $source
     */
    protected function assertEntityData(array $where, $visibility, $source)
    {
        $entityManager = $this->getResolvedVisibilityManager();
        /** @var ProductVisibilityResolved $entity */
        $entity = $entityManager->getRepository('OroB2BAccountBundle:VisibilityResolved\ProductVisibilityResolved')
            ->findOneBy($where);

        $this->assertNotNull($entity);
        $entityManager->refresh($entity);

        $this->assertEquals($visibility, $entity->getVisibility());
        $this->assertEquals($source, $entity->getSource());
    }

    /**
     * @param string $visibility
     * @return int|null
     */
    protected function resolveVisibility($visibility)
    {
        switch ($visibility) {
            case ProductVisibility::HIDDEN:
                return BaseProductVisibilityResolved::VISIBILITY_HIDDEN;
                break;
            case ProductVisibility::VISIBLE:
                return BaseProductVisibilityResolved::VISIBILITY_VISIBLE;
                break;
            default:
                return null;
        }
    }

    /**
     * @param array $actual
     * @param Website $website
     */
    protected function assertInsertedByCategory(array $actual, Website $website = null)
    {
        $pv = $this->getProductVisibilities();
        $products = $this->getProducts();
        $websites = $website ? [$website] : $this->getWebsites();

        foreach ($products as $product) {
            foreach ($websites as $website) {
                if (array_filter($pv, function (ProductVisibility $item) use ($website, $product) {
                    return $website === $item->getWebsite() && $product === $item->getProduct();
                })) {
                    continue;
                }

                $expected = [
                    'website' => $website->getId(),
                    'product' => $product->getId(),
                    'sourceProductVisibility' => null,
                    'visibility' => BaseProductVisibilityResolved::VISIBILITY_VISIBLE,
                    'source' => ProductVisibilityResolved::SOURCE_CATEGORY,
                    'category' => $this->getCategoryRepository()->findOneByProduct($product)->getId()
                ];
                $this->assertContains($expected, $actual);
            }
        }
    }

    /**
     * @param array $actual
     */
    protected function assertInsertedFromBaseTable(array $actual)
    {
        foreach ($this->getProductVisibilities() as $pv) {
            $visibility = $this->resolveVisibility($pv->getVisibility());

            if (null !== $visibility) {
                $expected = [
                    'website' => $pv->getWebsite()->getId(),
                    'product' => $pv->getProduct()->getId(),
                    'sourceProductVisibility' => $pv->getId(),
                    'visibility' => $visibility,
                    'source' => ProductVisibilityResolved::SOURCE_STATIC,
                    'category' => null
                ];
                $this->assertContains($expected, $actual);
            }
        }
    }

    /**
     * @return Product[]
     */
    protected function getProducts()
    {
        return $this->getProductRepository()->findAll();
    }

    /**
     * @return ProductRepository
     */
    protected function getProductRepository()
    {
        $className = $this->getContainer()->getParameter('orob2b_product.product.class');

        return $this->getContainer()->get('doctrine')
            ->getManagerForClass($className)
            ->getRepository('OroB2BProductBundle:Product');
    }

    /**
     * @return array
     */
    protected function getActualArray()
    {
        return $this->repository->createQueryBuilder('pvr')
            ->select(
                'IDENTITY(pvr.website) as website',
                'IDENTITY(pvr.product) as product',
                'IDENTITY(pvr.sourceProductVisibility) as sourceProductVisibility',
                'pvr.visibility as visibility',
                'pvr.source as source',
                'IDENTITY(pvr.category) as category'
            )
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array
     */
    protected function getActual()
    {
        return $this->repository->findAll();
    }

    /**
     * @return ObjectManager
     */
    protected function getResolvedVisibilityManager()
    {
        $className = $this->getContainer()->getParameter('orob2b_account.entity.product_visibility_resolved.class');

        return $this->getContainer()->get('doctrine')
            ->getManagerForClass($className);
    }

    /**
     * @return CategoryRepository
     */
    protected function getCategoryRepository()
    {
        $className = $this->getContainer()->getParameter('orob2b_catalog.category.class');

        return $this->getContainer()->get('doctrine')
            ->getManagerForClass($className)
            ->getRepository('OroB2BCatalogBundle:Category');
    }

    /**
     * @return Category[]
     */
    protected function getCategories()
    {
        return $this->getCategoryRepository()->findAll();
    }

    /**
     * @return ProductVisibility[]
     */
    protected function getProductVisibilities()
    {
        return $this->getContainer()->get('doctrine')
            ->getRepository('OroB2BAccountBundle:Visibility\ProductVisibility')
            ->findAll();
    }

    /**
     * @return Website[]
     */
    protected function getWebsites()
    {
        $className = $this->getContainer()->getParameter('orob2b_website.website.class');
        $repository = $this->getContainer()->get('doctrine')
            ->getManagerForClass($className)
            ->getRepository('OroB2BWebsiteBundle:Website');

        return $repository->findAll();
    }

    /**
     * @return InsertFromSelectQueryExecutor
     */
    protected function getInsertFromSelectExecutor()
    {
        return $this->getContainer()
            ->get('oro_entity.orm.insert_from_select_query_executor');
    }
}

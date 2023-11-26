<?php

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class Substitution
{
    private static $context;
    private static $product;
    private static $category;
    private static $minPrice;
    private static $maxPrice;
    private static $maxPriceAll;
    private static $minPriceAll;
    private static $cms;
    private static $manufacturer;

    public static function init($context)
    {
        self::$context = $context;
        if ((int)Tools::getValue('id_product')) {
            self::$product = new Product((int)Tools::getValue('id_product'), false, $context->language->id ?: 1);

            if (Validate::isLoadedObject(self::getProduct())) {
                self::$category = new Category(self::getProduct()->id_category_default, $context->language->id ?: 1);
                self::$manufacturer = new Manufacturer(self::getProduct()->id_manufacturer, $context->language->id ?: 1);
            }
        }

        if ((int)Tools::getValue('id_cms')) {
            self::$cms = new CMS((int)Tools::getValue('id_cms'), $context->language->id ?: 1);
        }

        if ((int)Tools::getValue('id_manufacturer')) {
            self::$manufacturer = new Manufacturer((int)Tools::getValue('id_manufacturer'), $context->language->id ?: 1);
        }

        if ((int)Tools::getValue('id_category') || Tools::getValue('id_category_manufacturer')) {
            self::$category = new Category((int)Tools::getValue('id_category') ?: Tools::getValue('id_category_manufacturer'), $context->language->id ?: 1);
        }

        self::setPriceRange();
    }

    public static function setPriceRange()
    {
        $idShop = (int)self::getContext()->shop->id;

        $arrayProductsIds = [];

        if (self::getContext()->filtered_result) {
            $product_page = self::getContext()->filtered_result['products'];
            $arrayProductsIds['onPage'] = array_column($product_page, 'id_product');
            $arrayProductsIds['allPages'] = self::getContext()->filtered_result['products_all'];
        }

        foreach ($arrayProductsIds as $key => $productIds) {
            $mainId = is_null(self::$cms->id) ? self::$category->id : self::$cms->id;
            $mainKey = is_null(self::$cms->id) ? 'c' : 'cms';
            $cachePartNumber = intdiv($mainId, 10);
            $cacheKey = $mainKey . $cachePartNumber . 'key' . $key . 'minmaxprice';
            $priceByShop = \EOCache::getInstance()->get($cacheKey);

            if ($priceByShop === false || !isset($priceByShop[$idShop][$mainKey][$mainId])) {
                $query = (new DbQuery())
                    ->select('MAX(price) as max, MIN(price) as min')
                    ->from('product_shop')
                    ->where('id_shop = ' . $idShop)
                    ->where('price > 0')
                    ->where('id_product IN (' . implode(',', $productIds) . ')');

                $priceByShop[$idShop][$mainKey][$mainId] = Db::getInstance()->getRow($query);

                EOCache::getInstance()->set($cacheKey, $priceByShop);
            }

            if (isset($priceByShop[$idShop][$mainKey][$mainId])) {
                $prices = $priceByShop[$idShop][$mainKey][$mainId];

                if ($key == 'onPage') {
                    self::$maxPrice = $prices['max'];
                    self::$minPrice = $prices['min'];
                } else {
                    self::$maxPriceAll = $prices['max'];
                    self::$minPriceAll = $prices['min'];
                }
            }
        }
    }

    public static function getProduct()
    {
        return self::$product;
    }

    public static function setCategory($category)
    {
        self::$category = $category;
    }

    public static function getCategory()
    {
        return self::$category;
    }

    public static function getCms()
    {
        return self::$cms;
    }

    public static function setManufacturer($manufacturer)
    {
        self::$manufacturer = $manufacturer;
    }

    public static function getManufacturer()
    {
        return self::$manufacturer;
    }

    public static function getMinPrice($format = true, $all = false)
    {
        if (!$all) {
            $price = self::$minPrice;
        } else {
            $price = self::$minPriceAll;
        }
        return $format ? Product::priceFormat($price) : $price;
    }

    public static function getMaxPrice($format = true, $all = false)
    {
        if (!$all) {
            $price = self::$maxPrice;
        } else {
            $price = self::$maxPriceAll;
        }
        return $format ? Product::priceFormat($price) : $price;
    }

    public static function getBrandsCount()
    {
        return (int) (self::$context->filtered_result['brands_count'] ?? 0);
    }


    public static function getCity()
    {
        return self::getContext()->city;
    }

    public static function getContext()
    {
        return self::$context;
    }

    public static function getProductName($fullName = false)
    {
        $colorName = Product::getCurrentAttributeName(self::getProduct());

        $colorName = $colorName ? str_replace('/', ', ', $colorName) : '';

        if (Validate::isLoadedObject(self::getProduct())) {
            if (!$fullName && isset(self::getProduct()->alt_name) && !empty(self::getProduct()->alt_name)) {
                $productName = self::getProduct()->alt_name;
            } elseif (isset(self::getProduct()->name) && !empty(self::getProduct()->name)) {
                $productName = self::getProduct()->name;
            } else {
                $productName = Product::getProductName(self::getProduct()->id, null, null, true);
            }
        }

        if (isset($productName) && is_array($productName)) {
            $productName = reset($productName);
        }

        return (isset($productName)  ? $productName  : '') . ($colorName ? ' ' . $colorName : '');
    }

    public static function getProductReference()
    {
        if (Validate::isLoadedObject(self::getProduct())) {
            return self::getProduct()->reference;
        }
        return '';
    }

    public static function getProductPriceFormat()
    {
        $price = self::getProductPrice();
        return Product::priceFormat($price, self::getContext()->currency);
    }

    public static function getProductPrice()
    {
        if (Validate::isLoadedObject(self::getProduct())) {
            $productPrice = Product::getPriceStatic(self::getProduct()->id, true, null, _PS_PRICE_DISPLAY_PRECISION_);
        } elseif (Validate::isLoadedObject(self::getCategory())) {
            $productPrice = Tools::ps_round(self::getCategory()->price, _PS_PRICE_DISPLAY_PRECISION_);
        } elseif (!is_null(self::getMinPrice())) {
            $productPrice = self::getMinPrice();
        }

        return isset($productPrice) ? $productPrice : null;
    }

    public static function getManufacturerName()
    {
        return Validate::isLoadedObject(self::getManufacturer()) ? self::getManufacturer()->name : 'Список брендов';
    }

    public static function getCategoryName()
    {
        return Validate::isLoadedObject(self::getCategory()) ? self::getCategory()->name : 'Список товаров';
    }

    public static function getCmsName()
    {
        return Validate::isLoadedObject(self::getCms()) ? self::getCms()->name : 'Список товаров';
    }

    public static function getCityFormat($format)
    {
        return Validate::isLoadedObject(self::getCity()) ? self::getCity()->cases_format[$format] : '';
    }

    public static function getCurrency()
    {
        return self::getContext()->currency->symbol;
    }

    public static function getCatalogType()
    {
        // Название категории выбранной в брендах
        if ($categoryName = Tools::getValue('category_name')) {
            return $categoryName;
        }

        $type = Tools::getValue('type');

        if (!$type) {
            return Context::getContext()->shop->id_shop_group == 3 ? '' : 'Мебель';
        }

        if ($type == 1) {
            return 'Мебель';
        }

        if ($type == 2) {
            return 'Канцтовары';
        }

        if ($type == 3) {
            return 'Оргтехника';
        }
    }

    public static function getCatalogTypeGenitive()
    {
        $type = Tools::getValue('type');

        if (!$type) {
            return Context::getContext()->shop->id_shop_group == 3 ? '' : 'Мебели';
        }

        if ($type == 1) {
            return 'Мебели';
        }

        if ($type == 2) {
            return 'Канцтоваров';
        }

        if ($type == 3) {
            return 'Оргтехники';
        }
    }

    public static function getPageTitle()
    {
        $pageTitle = '';

        switch (Context::getContext()->controller->getPageName()) {
            case 'product':
                $product = self::getProduct();
                $pageTitle = $product->name  . ' ' . Product::getCurrentAttributeName($product);
                break;
            case 'category':
                $pageTitle = self::getCategoryName();
                break;
            case 'manufacturer':
                $category = self::getCategory();
                $manufacturer = self::getManufacturer();
                $pageTitle = '';
                if ($category) {
                    if ($manufacturer) {
                        $pageTitle .= $category->name . ' ' . $manufacturer->name;
                    } else {
                        $pageTitle .= $category->name;
                    }
                } else {
                    if ($manufacturer) {
                        $pageTitle .= $manufacturer->name;
                    }
                }
                break;
            case 'cms':
                $pageTitle = self::getCmsName();
                break;
        }

        return $pageTitle;
    }

    protected static function getManufacturerCountry($features)
    {
        foreach ($features as $property) {
            if ($property['name'] == "Страна производитель") {
                return $property['value'];
            }
        }

        return "";
    }

    public static function replace($replaceList, $context)
    {
        self::init($context);

        $isCity = (class_exists('City') && self::getCity() instanceof City);

        if (self::getCms()) {
            $pageH1 = self::getCmsName();
        } elseif (self::getCategory() && !self::getCms()) {
            $pageH1 = self::getCategoryName();
        } else {
            $pageH1 = '';
        }

        $container = SymfonyContainer::getInstance();
        $replaceArray = [
            '%city%'                    => $isCity ? self::getCityFormat(City::CASE_PREPOSITIONAL) : null,
            '%city_0%'                  => $isCity ? self::getCityFormat(City::CASE_NOMINATIVE)    : null,
            '%city_1%'                  => $isCity ? self::getCityFormat(City::CASE_GENITIVE)      : null,
            '%city_2%'                  => $isCity ? self::getCityFormat(City::CASE_DATIVE)        : null,
            '%city_3%'                  => $isCity ? self::getCityFormat(City::CASE_ACCUSATIVE)    : null,
            '%city_4%'                  => $isCity ? self::getCityFormat(City::CASE_INSTRUMENTAL)  : null,
            '%city_5%'                  => $isCity ? self::getCityFormat(City::CASE_PREPOSITIONAL) : null,
            '%product%'                 => self::getProductName(),
            '%productFull%'             => self::getProductName(true),
            '%product_reference%'       => self::getProductReference(),
            '%number_of_products%'      => self::$context->filtered_result['total'],
            '%price%'                   => self::getProductPrice(),
            '%price_min%'               => self::getMinPrice(),
            '%price_min_noFormat%'      => Tools::toPluralForm(round(self::getMinPrice(false)), ['рубль', 'рубля', 'рублей']),
            '%price_min_all%'           => self::getMinPrice(true, true),
            '%price_min_all_noFormat%'  => Tools::toPluralForm(round(self::getMinPrice(false, true)), ['рубль', 'рубля', 'рублей']),
            '%price_max%'               => self::getMaxPrice(),
            '%price_max_noFormat%'      => Tools::toPluralForm(round(self::getMaxPrice(false)), ['рубль', 'рубля', 'рублей']),
            '%price_max_all%'           => self::getMaxPrice(true, true),
            '%price_max_all_noFormat%'  => Tools::toPluralForm(round(self::getMaxPrice(false, true)), ['рубль', 'рубля', 'рублей']),
            '%priceFormat%'             => self::getProductPriceFormat(),
            '%brand%'                   => self::getManufacturerName(),
            '%category%'                => self::getCategoryName(),
            '%cms%'                     => self::getCmsName(),
            '%currency%'                => self::getCurrency(),
            '%title%'                   => self::getPageTitle(),
            '%catalog_type%'            => self::getCatalogType(),
            '%catalog_type_genitive%'   => self::getCatalogTypeGenitive(),
            '%shop_type%'               => $context->shop->id_shop_group == 3 ? 'Home24' : 'Экспресс Офис',
            '%countProducts%'           => Tools::toPluralForm(self::$context->filtered_result['total'], ['товар', 'товара', 'товаров']),
            '%countBrand%'              => self::getBrandsCount() ? self::getBrandsCount() : 0,
            '%countBrand_brands%'       => Tools::toPluralForm(self::getBrandsCount(), ['бренд', 'бренда', 'брендов']),
            '%currentH1%'               => $pageH1,
            '%letter%'                  => Tools::getValue('letter'),
            '%count_color%'             => self::$context->filtered_result['collectionVariablesForSeo']['count_colors'] ?? null,
            '%count_color_coloration%'  => Tools::toPluralForm(self::$context->filtered_result['collectionVariablesForSeo']['count_colors'], ['расцветка', 'расцветки', 'расцветок']) ?? null,
            '%min_width%'               => self::$context->filtered_result['collectionVariablesForSeo']['min_width'] ?? null,
            '%max_width%'               => self::$context->filtered_result['collectionVariablesForSeo']['max_width'] ?? null,
            '%min_depth%'               => self::$context->filtered_result['collectionVariablesForSeo']['min_depth'] ?? null,
            '%max_depth%'               => self::$context->filtered_result['collectionVariablesForSeo']['max_depth'] ?? null,
            '%min_height%'              => self::$context->filtered_result['collectionVariablesForSeo']['min_height'] ?? null,
            '%max_height%'              => self::$context->filtered_result['collectionVariablesForSeo']['max_height'] ?? null,
            '%countProducts_variant%'   => Tools::toPluralForm(self::$context->filtered_result['total'], ['вариант', 'варианта', 'вариантов']),
            '%countProducts_solution%'  => Tools::toPluralForm(self::$context->filtered_result['total'], ['решение', 'решения', 'решений']),
            '%countProducts_position%'  => Tools::toPluralForm(self::$context->filtered_result['total'], ['позиция', 'позиции', 'позиций']),
            '%countProducts_model%'     => Tools::toPluralForm(self::$context->filtered_result['total'], ['модель', 'модели', 'моделей']),
            '%countProducts_collection%' => Tools::toPluralForm(self::$context->filtered_result['total'], ['коллекция', 'коллекции', 'коллекций']),
            '%countProducts_variety%'   => Tools::toPluralForm(self::$context->filtered_result['total'], ['разновидность', 'разновидности', 'разновидностей']),
        ];

        foreach ($replaceArray as $id => $row) {
            if ($row === null) {
                unset($replaceArray[$id]);
            }
        }

        $replaceList = Tools::replace($replaceList, $replaceArray);

        if ($product = self::getProduct()) {
            $repository = $container->get('product_comment_repository');
            $productComments = $repository->getProductComments($product->id_product);
            $raitsByStars = ProductComment::getProductGrades($product);
            $allFeaturesProduct = $product->getAllFeaturesProduct();
            $context = self::getContext();
            $row = [
                'id_product'            => $product->id_product,
                'id_attribute'          => $product->id_attribute,
                'type'                  => $product->type,
                'id_supplier'           => $product->id_supplier,
                'weight'                => $product->weight,
                'width'                 => (int)$product->width,
                'height'                => (int)$product->height,
                'depth'                 => (int)$product->depth,
                'volume'                => $product->volume,
                'available_for_order'   => 1,
            ];
            $productProperties = Product::getProductProperties($context->language->id, $row, $context);

            $images = new Emarketing\Service\Products\Images();

            $averageGrade = $repository->getCommentsForSchema(
                $product->id_product,
            );

            $replaceList = Tools::replace($replaceList, [
                '%rating%'                        => $averageGrade['average_grade'],
                '%product_code%'                  => $product->id_product,
                '%number_of_reviews_and_ratings%' => $productComments['grade_nb'] + $productComments['comments_nb'],
                '%number_of_ratings%'             => $productComments['grade_nb'],
                '%number_of_ratings_5_stars%'     => $raitsByStars[0]['count'],
                '%number_of_ratings_4_stars%'     => $raitsByStars[1]['count'],
                '%guarantee%'                     => $allFeaturesProduct['guarantee']['value'],
                '%number_of_product_colors%'      => $productProperties['attributes']['count'],
                '%the_price_of_the_product%'      => $productProperties['price'],
                '%manufacturer_country%'          => self::getManufacturerCountry($productProperties['features']),
                '%product_assembly_cost%'         => $productProperties['delivery_cost'],
                '%estimated_delivery_time%'       => $productProperties['delivery_dates'][0],
                '%number_of_product_photos%'      => count($images->buildImageInformation($product, $context->language->id)),
                '%min_cost_of_delivery%'          => self::getProductName(),
                '%number_of_goods_on_balance%'    => $productProperties['quantity'],
                '%item_number%'                   => $product->reference,
                '%weight%'                        => round($product->weight, 2),
                '%product_main_color%'            => Attribute::getMetaTitle($product->id_attribute),
                '%amount_of_discount%'            => $productProperties['discount_percentage'],
            ]);
        }

        return $replaceList;
    }
}

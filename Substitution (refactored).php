<?php

class Substitution
{
    /** @var Context $context */
    private static $context;
    /** @var Product $product */
    private static $product;
    /** @var Category $category */
    private static $category;
    /** @var CMS $cms */
    private static $cms;
    /** @var Manufacturer $manufacturer */
    private static $manufacturer;

    private const PLURAL_PRODUCTS     = 'products';
    private const PLURAL_VARIANTS     = 'variants';
    private const PLURAL_SOLUTIONS    = 'solutions';
    private const PLURAL_POSITIONS    = 'positions';
    private const PLURAL_MODELS       = 'models';
    private const PLURAL_COLLECTIONS  = 'collections';
    private const PLURAL_VARIETIES    = 'varieties';

    private static $replacedDataCache = [];
    private static $propertiesCache   = [];
    private static $pluralsList       = [
        self::PLURAL_PRODUCTS    => ['товар', 'товара', 'товаров'],
        self::PLURAL_VARIANTS    => ['вариант', 'варианта', 'вариантов'],
        self::PLURAL_SOLUTIONS   => ['решение', 'решения', 'решений'],
        self::PLURAL_POSITIONS   => ['позиция', 'позиции', 'позиций'],
        self::PLURAL_MODELS      => ['модель', 'модели', 'моделей'],
        self::PLURAL_COLLECTIONS => ['коллекция', 'коллекции', 'коллекций'],
        self::PLURAL_VARIETIES   => ['разновидность', 'разновидности', 'разновидностей'],
    ];

    public static function init($context)
    {
        self::$context = $context;

        switch (self::$context->controller->getPageName()) {
            case 'product':
                self::$product = self::$product ?: new Product((int)Tools::getValue('id_product'));
                break;
            case 'category':
                self::$category = self::$category ?: new Category((int)Tools::getValue('id_category'));
                break;
            case 'manufacturer':
                self::$manufacturer = self::$manufacturer ?: new Manufacturer((int)Tools::getValue('id_manufacturer'));

                if (Tools::getValue('id_category_manufacturer')) {
                    self::$category = self::$category ?: new Category((int)Tools::getValue('id_category'));
                }
                break;
            case 'cms':
                self::$cms = self::$cms ?: new CMS((int)Tools::getValue('id_cms'));
                break;
        }
    }

    /**
     * @deprecated $all
     */
    protected static function getPriceRange($all = false)
    {
        return [
            'min' => self::getCollectionVariableForSeo('min_price'),
            'max' => self::getCollectionVariableForSeo('max_price'),
        ];
    }

    public static function getMinPrice($format = true, $all = false, $formatWhithoutSign = false)
    {
        $priceRange = self::getPriceRange($all);

        $price = $priceRange['min'] ?? 0;

        if (!$format || !$price) {
            return $price;
        }

        if ($formatWhithoutSign) {
            return Tools::toPluralForm(round($price, 2), ['рубль', 'рубля', 'рублей']);
        }

        return Product::priceFormat($price);
    }

    public static function getMaxPrice($format = true, $all = false, $formatWhithoutSign = false)
    {
        $priceRange = self::getPriceRange($all);

        $price = $priceRange['max'] ?? 0;

        if (!$format || !$price) {
            return $price;
        }

        if ($formatWhithoutSign) {
            return Tools::toPluralForm(round($price, 2), ['рубль', 'рубля', 'рублей']);
        }

        return Product::priceFormat($price);
    }

    protected static function getBrandsCount($format = true)
    {
        $count = (int) (self::$context->filtered_result['brands_count'] ?? 0);

        if (!$format) {
            return $count;
        }

        return Tools::toPluralForm($count, ['бренд', 'бренда', 'брендов']);
    }

    protected static function getCity()
    {
        return self::$context->city;
    }

    protected static function getProductName($fullName = false)
    {
        $colorName = Product::getCurrentAttributeName(self::$product);

        $colorName = $colorName ? str_replace('/', ', ', $colorName) : '';

        if (Validate::isLoadedObject(self::$product)) {
            if (!$fullName && isset(self::$product->alt_name) && !empty(self::$product->alt_name)) {
                $productName = self::$product->alt_name;
            } elseif (isset(self::$product->name) && !empty(self::$product->name)) {
                $productName = self::$product->name;
            } else {
                $productName = Product::getProductName(self::$product->id, null, null, true);
            }
        }

        if (isset($productName) && is_array($productName)) {
            $productName = reset($productName);
        }

        return (isset($productName)  ? $productName  : '') . ($colorName ? ' ' . $colorName : '');
    }

    protected static function getProductReference()
    {
        if (Validate::isLoadedObject(self::$product)) {
            return self::$product->reference;
        }
        return '';
    }

    protected static function getProductPrice($format = true)
    {
        if (Validate::isLoadedObject(self::$product)) {
            $productPrice = Product::getPriceStatic(self::$product->id, true, null, _PS_PRICE_DISPLAY_PRECISION_);
        } elseif (Validate::isLoadedObject(self::$category)) {
            $productPrice = Tools::ps_round(self::$category->price, _PS_PRICE_DISPLAY_PRECISION_);
        } elseif (!is_null(self::getMinPrice())) {
            $productPrice = self::getMinPrice();
        }

        if (!$productPrice) {
            return 0;
        }

        return $format ? Product::priceFormat($productPrice, self::$context->currency) : $productPrice;
    }

    protected static function getManufacturerName()
    {
        return Validate::isLoadedObject(self::$manufacturer) ? self::$manufacturer->name : 'Список брендов';
    }

    protected static function getCategoryName()
    {
        return Validate::isLoadedObject(self::$category) ? self::$category->name : 'Список товаров';
    }

    protected static function getCmsName()
    {
        return Validate::isLoadedObject(self::$cms) ? self::$cms->name : 'Список товаров';
    }

    protected static function getCityFormat($format)
    {
        $isCity = (class_exists('City') && self::getCity() instanceof City);

        if (!$isCity) {
            return '';
        }

        return Validate::isLoadedObject(self::getCity()) ? self::getCity()->cases_format[$format] : '';
    }

    protected static function getCurrency()
    {
        return self::$context->currency->symbol;
    }

    public static function getCatalogType()
    {
        // Название категории выбранной в брендах
        if ($categoryName = Tools::getValue('category_name')) {
            return $categoryName;
        }

        $type = Tools::getValue('type');

        if (!$type) {
            return self::$context->shop->id_shop_group == 3 ? '' : 'Мебель';
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
            return self::$context->shop->id_shop_group == 3 ? '' : 'Мебели';
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

    protected static function getPageTitle()
    {
        $pageTitle = '';

        switch (self::$context->controller->getPageName()) {
            case 'product':
                $product = self::$product;
                $pageTitle = $product->name  . ' ' . Product::getCurrentAttributeName($product);
                break;
            case 'category':
                $pageTitle = self::getCategoryName();
                break;
            case 'manufacturer':
                $category = self::$category;
                $manufacturer = self::$manufacturer;
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

    protected static function getProductFeature($value)
    {
        if (!Validate::isLoadedObject(self::$product)) {
            return '';
        }

        $cacheKey = 'p' . self::$product->id . 'sg' . self::$context->shop->id_shop_group . 'content-features';
        $cachedProductFeatures = \EOCache::getInstance()->get($cacheKey);

        if ($cachedProductFeatures === false) {
            $cachedProductFeatures = self::$product->getAllFeaturesProduct();
            \EOCache::getInstance()->set($cacheKey, $cachedProductFeatures);
        }

        if (!$cachedProductFeatures) {
            return '';
        }

        $cachedProductFeatures = array_column($cachedProductFeatures['features'], 'data');
        $cachedProductFeatures = call_user_func_array('array_merge', $cachedProductFeatures);

        foreach ($cachedProductFeatures as $property) {
            if ($property['name'] == $value) {
                return $property['value'];
            }
        }

        return '';
    }

    protected static function getPageH1()
    {
        $pageH1 = '';

        switch (self::$context->controller->getPageName()) {
            case 'product':
                $pageH1 = self::getProductName();
                break;
            case 'category':
                $pageH1 = self::getCategoryName();
                break;
            case 'manufacturer':
                $pageH1 = self::getManufacturerName();
                break;
            case 'cms':
                $pageH1 = self::getCmsName();
                break;
        }

        return $pageH1;
    }

    protected static function getTotalNumberOfProdcuts($type = 'none')
    {
        if (!isset(self::$context->filtered_result['total'])) {
            return 0;
        }

        if (!in_array($type, array_keys(self::$pluralsList))) {
            return (int) self::$context->filtered_result['total'];
        }

        $plurals = self::$pluralsList[$type];

        return Tools::toPluralForm((int) self::$context->filtered_result['total'], $plurals);
    }

    protected static function getShopType()
    {
        return self::$context->shop->id_shop_group == 3 ? 'Home24' : 'Экспресс Офис';
    }

    protected static function getLetter()
    {
        return Tools::getValue('letter') ?: '';
    }

    protected static function getCollectionVariableForSeo($variable, $formatColors = true)
    {
        if (!isset(self::$context->filtered_result['collectionVariablesForSeo'])) {
            return '';
        }

        $value = self::$context->filtered_result['collectionVariablesForSeo'][$variable] ?? '';

        if (!$value) {
            return '';
        }

        if ($variable === 'count_colors') {
            return $formatColors ? Tools::toPluralForm($value, ['расцветка', 'расцветки', 'расцветок']) : $value;
        }

        return $value;
    }

    protected static function getProductCode()
    {
        if (!Validate::isLoadedObject(self::$product)) {
            return 0;
        }

        return self::$product->id;
    }

    protected static function getAverageGrade()
    {
        if (!Validate::isLoadedObject(self::$product)) {
            return 0;
        }

        $commentsData = Product::getProductCommentsData(self::$product->id);

        return $commentsData['average_grade'];
    }

    protected static function getCommentsAndRatingsCount()
    {
        if (!Validate::isLoadedObject(self::$product)) {
            return 0;
        }

        $commentsData = Product::getProductCommentsData(self::$product->id);

        return $commentsData['comments_count'] + $commentsData['grades_count'];
    }

    protected static function getRatingsCount()
    {
        if (!Validate::isLoadedObject(self::$product)) {
            return 0;
        }

        $commentsData = Product::getProductCommentsData(self::$product->id);

        return $commentsData['grades_count'];
    }

    protected static function getProductCommentsGrades($gradeIndex)
    {
        if (!Validate::isLoadedObject(self::$product)) {
            return 0;
        }

        require_once(_PS_MODULE_DIR_ . 'productcomments/ProductComment.php');

        $grades = ProductComment::getProductGrades(self::$product);

        if (!is_array($grades)) {
            return 0;
        }

        return isset($grades[$gradeIndex]) ? $grades[$gradeIndex]['count'] : 0;
    }

    protected static function getAttributesCount()
    {
        if (!Validate::isLoadedObject(self::$product)) {
            return 0;
        }

        $attributesData = Product::getAttributesCount(self::$product->id);

        return $attributesData['count'] ?? 0;
    }

    protected static function getProductProperties($value)
    {
        if (!Validate::isLoadedObject(self::$product)) {
            return '';
        }

        if (!self::$propertiesCache) {
            $row = [
                'id_product'            => self::$product->id_product,
                'id_attribute'          => self::$product->id_attribute,
                'type'                  => self::$product->type,
                'id_supplier'           => self::$product->id_supplier,
                'weight'                => self::$product->weight,
                'width'                 => (int) self::$product->width,
                'height'                => (int) self::$product->height,
                'depth'                 => (int) self::$product->depth,
                'volume'                => self::$product->volume,
                'available_for_order'   => 1,
            ];

            self::$propertiesCache = Product::getProductProperties(self::$context->language->id, $row, self::$context);
        }

        if ($value === 'delivery_dates') {
            return self::$propertiesCache[$value][0] ?? 0;
        }

        return self::$propertiesCache[$value] ?? '';
    }

    protected static function getProductWeight()
    {
        if (!Validate::isLoadedObject(self::$product)) {
            return 0;
        }

        return round(self::$product->weight, 2) ?? 0;
    }

    protected static function getProductMainColor()
    {
        if (!Validate::isLoadedObject(self::$product)) {
            return '';
        }

        return Attribute::getMetaTitle(self::$product->id_attribute);
    }


    protected static function getProductImagesCount()
    {
        if (!Validate::isLoadedObject(self::$product)) {
            return 0;
        }

        $images = new Emarketing\Service\Products\Images();

        $images = $images->buildImageInformation(self::$product, self::$context->language->id);

        return isset($images['product']) ? count($images['product']) : 0;
    }

    public static function replace($replaceList, $context)
    {
        self::init($context);

        if (!is_array($replaceList)) {
            return self::makeReplcament($replaceList);
        }

        foreach ($replaceList as $key => $item) {
            $replaceList[$key] = self::makeReplcament($item);
        }

        return $replaceList;
    }

    protected static function makeReplcament($string)
    {
        preg_match_all('/%.+?%/m', $string, $matches, PREG_SET_ORDER);

        if (!$matches) {
            return $string;
        }

        $matches = array_merge(...$matches);
        $matches = array_unique($matches);

        $replacementsList = self::getReplacmentsList();

        $replacements = [];

        foreach ($matches as $item) {
            if (!isset($replacementsList[$item])) {
                continue;
            }

            if (isset(self::$replacedDataCache[$item]) && self::$replacedDataCache[$item]) {
                $replacements[] = self::$replacedDataCache[$item];
                continue;
            }

            self::$replacedDataCache[$item] = call_user_func_array(
                "self::{$replacementsList[$item]['fn']}",
                $replacementsList[$item]['args']
            );

            $replacements[] = self::$replacedDataCache[$item];
        }

        return str_replace($matches, $replacements, $string);
    }

    protected static function getReplacmentsList()
    {
        return [
            '%city%'                          => ['fn' => 'getCityFormat',               'args' => [City::CASE_PREPOSITIONAL]],
            '%city_0%'                        => ['fn' => 'getCityFormat',               'args' => [City::CASE_NOMINATIVE]],
            '%city_1%'                        => ['fn' => 'getCityFormat',               'args' => [City::CASE_GENITIVE]],
            '%city_2%'                        => ['fn' => 'getCityFormat',               'args' => [City::CASE_DATIVE]],
            '%city_3%'                        => ['fn' => 'getCityFormat',               'args' => [City::CASE_ACCUSATIVE]],
            '%city_4%'                        => ['fn' => 'getCityFormat',               'args' => [City::CASE_INSTRUMENTAL]],
            '%city_5%'                        => ['fn' => 'getCityFormat',               'args' => [City::CASE_PREPOSITIONAL]],
            '%number_of_products%'            => ['fn' => 'getTotalNumberOfProdcuts',    'args' => []],
            '%price_min%'                     => ['fn' => 'getMinPrice',                 'args' => []],
            '%price_min_noFormat%'            => ['fn' => 'getMinPrice',                 'args' => [true, false, true]],
            '%price_min_all%'                 => ['fn' => 'getMinPrice',                 'args' => [true, true]],
            '%price_min_all_noFormat%'        => ['fn' => 'getMinPrice',                 'args' => [true, true, true]],
            '%price_max%'                     => ['fn' => 'getMaxPrice',                 'args' => []],
            '%price_max_noFormat%'            => ['fn' => 'getMaxPrice',                 'args' => [true, false, true]],
            '%price_max_all%'                 => ['fn' => 'getMaxPrice',                 'args' => [true, true]],
            '%price_max_all_noFormat%'        => ['fn' => 'getMaxPrice',                 'args' => [true, true, true]],
            '%brand%'                         => ['fn' => 'getManufacturerName',         'args' => []],
            '%category%'                      => ['fn' => 'getCategoryName',             'args' => []],
            '%cms%'                           => ['fn' => 'getCmsName',                  'args' => []],
            '%currency%'                      => ['fn' => 'getCurrency',                 'args' => []],
            '%title%'                         => ['fn' => 'getPageTitle',                'args' => []],
            '%catalog_type%'                  => ['fn' => 'getCatalogType',              'args' => []],
            '%catalog_type_genitive%'         => ['fn' => 'getCatalogTypeGenitive',      'args' => []],
            '%shop_type%'                     => ['fn' => 'getShopType',                 'args' => []],
            '%countBrand%'                    => ['fn' => 'getBrandsCount',              'args' => [false]],
            '%countBrand_brands%'             => ['fn' => 'getBrandsCount',              'args' => []],
            '%currentH1%'                     => ['fn' => 'getPageH1',                   'args' => []],
            '%letter%'                        => ['fn' => 'getLetter',                   'args' => []],
            '%count_color%'                   => ['fn' => 'getCollectionVariableForSeo', 'args' => ['count_colors']],
            '%count_color_coloration%'        => ['fn' => 'getCollectionVariableForSeo', 'args' => ['count_colors', true]],
            '%min_width%'                     => ['fn' => 'getCollectionVariableForSeo', 'args' => ['min_width']],
            '%max_width%'                     => ['fn' => 'getCollectionVariableForSeo', 'args' => ['max_width']],
            '%min_depth%'                     => ['fn' => 'getCollectionVariableForSeo', 'args' => ['min_depth']],
            '%max_depth%'                     => ['fn' => 'getCollectionVariableForSeo', 'args' => ['max_depth']],
            '%min_height%'                    => ['fn' => 'getCollectionVariableForSeo', 'args' => ['min_height']],
            '%max_height%'                    => ['fn' => 'getCollectionVariableForSeo', 'args' => ['max_height']],
            '%countProducts%'                 => ['fn' => 'getTotalNumberOfProdcuts',    'args' => [self::PLURAL_PRODUCTS]],
            '%countProducts_variant%'         => ['fn' => 'getTotalNumberOfProdcuts',    'args' => [self::PLURAL_VARIANTS]],
            '%countProducts_solution%'        => ['fn' => 'getTotalNumberOfProdcuts',    'args' => [self::PLURAL_SOLUTIONS]],
            '%countProducts_position%'        => ['fn' => 'getTotalNumberOfProdcuts',    'args' => [self::PLURAL_POSITIONS]],
            '%countProducts_model%'           => ['fn' => 'getTotalNumberOfProdcuts',    'args' => [self::PLURAL_MODELS]],
            '%countProducts_collection%'      => ['fn' => 'getTotalNumberOfProdcuts',    'args' => [self::PLURAL_COLLECTIONS]],
            '%countProducts_variety%'         => ['fn' => 'getTotalNumberOfProdcuts',    'args' => [self::PLURAL_VARIETIES]],
            // product
            '%rating%'                        => ['fn' => 'getAverageGrade',             'args' => []],
            '%product%'                       => ['fn' => 'getProductName',              'args' => []],
            '%productFull%'                   => ['fn' => 'getProductName',              'args' => [true]],
            '%product_reference%'             => ['fn' => 'getProductReference',         'args' => []],
            '%price%'                         => ['fn' => 'getProductPrice',             'args' => [false]],
            '%priceFormat%'                   => ['fn' => 'getProductPrice',             'args' => []],
            '%product_code%'                  => ['fn' => 'getProductCode',              'args' => []],
            '%number_of_reviews_and_ratings%' => ['fn' => 'getCommentsAndRatingsCount',  'args' => []],
            '%number_of_ratings%'             => ['fn' => 'getRatingsCount',             'args' => []],
            '%number_of_ratings_5_stars%'     => ['fn' => 'getProductCommentsGrades',    'args' => [0]],
            '%number_of_ratings_4_stars%'     => ['fn' => 'getProductCommentsGrades',    'args' => [1]],
            '%guarantee%'                     => ['fn' => 'getProductFeature',           'args' => ['Гарантия']],
            '%manufacturer_country%'          => ['fn' => 'getProductFeature',           'args' => ['Страна производитель']],
            '%number_of_product_colors%'      => ['fn' => 'getAttributesCount',          'args' => []],
            '%number_of_product_photos%'      => ['fn' => 'getProductImagesCount',       'args' => []],
            '%weight%'                        => ['fn' => 'getProductWeight',            'args' => []],
            '%product_main_color%'            => ['fn' => 'getProductMainColor',         'args' => []],
            '%min_cost_of_delivery%'          => ['fn' => 'getProductName',              'args' => []],
            '%product_assembly_cost%'         => ['fn' => 'getProductProperties',        'args' => ['delivery_cost']],
            '%estimated_delivery_time%'       => ['fn' => 'getProductProperties',        'args' => ['delivery_dates']],
            '%number_of_goods_on_balance%'    => ['fn' => 'getProductProperties',        'args' => ['quantity']],
            '%amount_of_discount%'            => ['fn' => 'getProductProperties',        'args' => ['discount_percentage']],
        ];
    }
}

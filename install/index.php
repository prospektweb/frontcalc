<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

class prospektweb_frontcalc extends CModule
{
    public $MODULE_ID = 'prospektweb.frontcalc';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;
    public $MODULE_GROUP_RIGHTS = 'N';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('PROSPEKTWEB_FRONTCALC_MODULE_NAME') ?: 'PROSPEKT-WEB: FrontCalc';
        $this->MODULE_DESCRIPTION = Loc::getMessage('PROSPEKTWEB_FRONTCALC_MODULE_DESCRIPTION') ?: 'Модуль калькулятора печатной продукции';
        $this->PARTNER_NAME = 'PROSPEKT-WEB';
        $this->PARTNER_URI = 'https://prospektweb.ru';
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (!$this->checkDependencies()) {
            return false;
        }

        $step = (int)($_REQUEST['step'] ?? 1);

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                'Установка модуля ' . $this->MODULE_ID,
                __DIR__ . '/step.php'
            );
            return true;
        }

        ModuleManager::registerModule($this->MODULE_ID);

        try {
            $this->InstallFiles();
            $this->InstallDB();
            $this->patchAsproBasketFile();
        } catch (\Throwable $e) {
            $this->UnInstallFiles();
            ModuleManager::unRegisterModule($this->MODULE_ID);
            $APPLICATION->ThrowException($e->getMessage());
            return false;
        }

        $APPLICATION->IncludeAdminFile(
            'Установка модуля ' . $this->MODULE_ID,
            __DIR__ . '/step.php'
        );

        return true;
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        $step = (int)($_REQUEST['step'] ?? 1);

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                'Удаление модуля ' . $this->MODULE_ID,
                __DIR__ . '/unstep1.php'
            );
            return;
        }

        $removeData = (isset($_REQUEST['remove_data']) && $_REQUEST['remove_data'] === 'Y');

        $this->UnInstallDB($removeData);
        $this->UnInstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            'Удаление модуля ' . $this->MODULE_ID,
            __DIR__ . '/unstep2.php'
        );
    }

    public function InstallDB()
    {
        if (!$this->checkDependencies()) {
            throw new \RuntimeException('Не выполнены зависимости iblock/catalog/sale');
        }

        [$productsIblockId, $offersIblockId] = $this->resolveIblocks();

        Option::set($this->MODULE_ID, 'PRODUCTS_IBLOCK_ID', (string)$productsIblockId);
        Option::set($this->MODULE_ID, 'OFFERS_IBLOCK_ID', (string)$offersIblockId);
        Option::set($this->MODULE_ID, 'CALC_PROPERTY_CODE', 'FRONTCALC_CONFIG');

        $this->ensureCalcConfigProperty($productsIblockId, 'FRONTCALC_CONFIG');
        $this->registerAdminHandlers();

        return true;
    }

    public function UnInstallDB($removeData = false)
    {
        $productsIblockId = (int)Option::get($this->MODULE_ID, 'PRODUCTS_IBLOCK_ID', '0');
        $propertyCode = (string)Option::get($this->MODULE_ID, 'CALC_PROPERTY_CODE', 'FRONTCALC_CONFIG');

        $this->unregisterAdminHandlers();

        if ($removeData) {
            $this->removeCalcConfigProperty($productsIblockId, $propertyCode);
            Option::delete($this->MODULE_ID);
        } else {
            // Служебно помечаем, что uninstall прошёл с сохранением данных
            Option::set($this->MODULE_ID, 'UNINSTALLED_AT', date('c'));
        }

        return true;
    }

    protected function patchAsproBasketFile()
    {
        $basketPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/aspro.premier/lib/product/basket.php';
        if (!is_file($basketPath)) {
            throw new \RuntimeException('Не найден файл Aspro для патча: ' . $basketPath);
        }

        $content = (string)file_get_contents($basketPath);
        if ($content === '') {
            throw new \RuntimeException('Файл Aspro пуст или не читается: ' . $basketPath);
        }

        $startMarker = '/* FRONTCALC_BUTTON_START */';
        if (strpos($content, $startMarker) !== false) {
            return;
        }

        $anchorPattern = '#<\?(?:php)?\s*if\s*\(\s*\$arConfig\[\s*[\'"]SHOW_BASKET_LINK[\'"]\s*\]\s*===\s*[\'"]Y[\'"]\s*\)\s*:\s*\?>#i';
        if (!preg_match_all($anchorPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException(
                'Шаблон Aspro обновился: не найдены подходящие сигнатуры "<?if ($arConfig[\'SHOW_BASKET_LINK\'] === \'Y\'):?>" в файле ' . $basketPath
            );
        }

        $anchorMatches = $matches[0];

        $snippet = $this->buildFrontcalcBasketSnippet();
        $offsetShift = 0;
        $patchCount = 0;

        for ($i = 0; $i < count($anchorMatches); $i++) {
            $matchOffset = (int)$anchorMatches[$i][1] + $offsetShift;
            $insertPosition = $matchOffset;
            $content = substr($content, 0, $insertPosition) . $snippet . substr($content, $insertPosition);

            $offsetShift += strlen($snippet);
            $patchCount++;
        }

        if ($patchCount < 1) {
            throw new \RuntimeException(
                'Шаблон Aspro обновился: не удалось вставить FrontCalc перед SHOW_BASKET_LINK в файле ' . $basketPath
            );
        }

        if (@file_put_contents($basketPath, $content) === false) {
            throw new \RuntimeException('Не удалось записать патч в файл: ' . $basketPath);
        }
    }

    protected function buildFrontcalcBasketSnippet()
    {
        return "\n<?php /* FRONTCALC_BUTTON_START */ ?>\n"
            . "<?php if (\\Bitrix\\Main\\Loader::includeModule('prospektweb.frontcalc')): ?>\n"
            . "<?php \$frontcalcPayload = (new \\Prospektweb\\Frontcalc\\Service\\CalculatorAvailability())->getLightPayload((int)(\$arConfig['ITEM_ID'] ?? 0), (int)(\$arConfig['CATALOG_IBLOCK_ID'] ?? 0)); ?>\n"
            . "<?php if (!empty(\$frontcalcPayload['is_available'])): ?>\n"
            . "<button type=\"button\" class=\"frontcalc-calculate-button js-frontcalc-calculate\" data-frontcalc-product-id=\"<?= (int)\$frontcalcPayload['product_id'] ?>\" data-frontcalc-ajax-url=\"<?= htmlspecialcharsbx((string)\$frontcalcPayload['ajax_url']) ?>\">Рассчитать стоимость</button>\n"
            . "<?php endif; ?>\n"
            . "<?php endif; ?>\n"
            . "<?php /* FRONTCALC_BUTTON_END */ ?>\n";
    }

    protected function registerAdminHandlers()
    {
        EventManager::getInstance()->registerEventHandlerCompatible(
            'main',
            'OnAdminContextMenuShow',
            $this->MODULE_ID,
            '\\Prospektweb\\Frontcalc\\Admin\\ProductCardButton',
            'onAdminContextMenuShow'
        );
    }

    protected function unregisterAdminHandlers()
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnAdminContextMenuShow',
            $this->MODULE_ID,
            '\\Prospektweb\\Frontcalc\\Admin\\ProductCardButton',
            'onAdminContextMenuShow'
        );
    }

    protected function ensureCalcConfigProperty($productsIblockId, $propertyCode)
    {
        $productsIblockId = (int)$productsIblockId;
        $propertyCode = (string)$propertyCode;

        if ($productsIblockId <= 0 || $propertyCode === '' || !Loader::includeModule('iblock')) {
            return;
        }

        $existing = \CIBlockProperty::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => $productsIblockId, 'CODE' => $propertyCode]
        )->Fetch();

        if ($existing) {
            return;
        }

        $property = new \CIBlockProperty();
        $property->Add([
            'IBLOCK_ID' => $productsIblockId,
            'NAME' => 'Конфиг калькулятора',
            'ACTIVE' => 'Y',
            'SORT' => 500,
            'CODE' => $propertyCode,
            'PROPERTY_TYPE' => 'S',
            'USER_TYPE' => 'HTML',
            'MULTIPLE' => 'N',
            'IS_REQUIRED' => 'N',
        ]);
    }

    protected function removeCalcConfigProperty($productsIblockId, $propertyCode)
    {
        $productsIblockId = (int)$productsIblockId;
        $propertyCode = (string)$propertyCode;

        if ($productsIblockId <= 0 || $propertyCode === '' || !Loader::includeModule('iblock')) {
            return;
        }

        $existing = \CIBlockProperty::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => $productsIblockId, 'CODE' => $propertyCode]
        )->Fetch();

        if ($existing && isset($existing['ID'])) {
            \CIBlockProperty::Delete((int)$existing['ID']);
        }
    }

    public function InstallFiles()
    {
        $adminTarget = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/prospektweb_frontcalc_editor.php';
        $moduleAdminFile = dirname(__DIR__) . '/admin/prospektweb_frontcalc_editor.php';
        if (!is_file($moduleAdminFile)) {
            throw new \RuntimeException('Не найден файл admin/prospektweb_frontcalc_editor.php');
        }

        $content = "<?php\n"
            . "\$localPath = \$_SERVER['DOCUMENT_ROOT'] . '/local/modules/" . $this->MODULE_ID . "/admin/prospektweb_frontcalc_editor.php';\n"
            . "\$bitrixPath = \$_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/" . $this->MODULE_ID . "/admin/prospektweb_frontcalc_editor.php';\n"
            . "if (is_file(\$localPath)) {\n"
            . "    require_once \$localPath;\n"
            . "} elseif (is_file(\$bitrixPath)) {\n"
            . "    require_once \$bitrixPath;\n"
            . "} else {\n"
            . "    die('admin/prospektweb_frontcalc_editor.php not found');\n"
            . "}\n";
        if (@file_put_contents($adminTarget, $content) === false) {
            throw new \RuntimeException('Не удалось создать /bitrix/admin/prospektweb_frontcalc_editor.php');
        }

        return true;
    }

    public function UnInstallFiles()
    {
        $adminTarget = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/prospektweb_frontcalc_editor.php';
        if (is_file($adminTarget)) {
            @unlink($adminTarget);
        }

        return true;
    }

    protected function checkDependencies()
    {
        global $APPLICATION;

        $required = ['iblock', 'catalog', 'sale'];
        $errors = [];

        foreach ($required as $moduleId) {
            if (!Loader::includeModule($moduleId)) {
                $errors[] = 'Не найден обязательный модуль: ' . $moduleId;
            }
        }

        if (!empty($errors)) {
            $APPLICATION->ThrowException(implode('<br>', $errors));
            return false;
        }

        return true;
    }

    /**
     * Возвращает [PRODUCTS_IBLOCK_ID, OFFERS_IBLOCK_ID]
     */
    protected function resolveIblocks()
    {
        $productsIblockId = (int)($_REQUEST['PRODUCTS_IBLOCK_ID'] ?? 0);
        $offersIblockId = (int)($_REQUEST['OFFERS_IBLOCK_ID'] ?? 0);

        // 1) Если руками передали оба ID — используем их
        if ($productsIblockId > 0 && $offersIblockId > 0) {
            return [$productsIblockId, $offersIblockId];
        }

        // 2) Основной путь: таблица catalog_iblock (D7)
        if (Loader::includeModule('catalog')) {
            $row = \Bitrix\Catalog\CatalogIblockTable::getList([
                'select' => ['IBLOCK_ID', 'PRODUCT_IBLOCK_ID'],
                'filter' => ['!=PRODUCT_IBLOCK_ID' => 0],
                'order' => ['IBLOCK_ID' => 'ASC'],
                'limit' => 1,
            ])->fetch();

            if ($row) {
                $offersFromTable = (int)$row['IBLOCK_ID'];
                $productsFromTable = (int)$row['PRODUCT_IBLOCK_ID'];

                if ($offersIblockId <= 0) {
                    $offersIblockId = $offersFromTable;
                }
                if ($productsIblockId <= 0) {
                    $productsIblockId = $productsFromTable;
                }
            }
        }

        // 3) Если известен offers -> пытаемся получить product через CCatalogSKU
        if ($offersIblockId > 0 && $productsIblockId <= 0 && Loader::includeModule('catalog')) {
            $sku = \CCatalogSKU::GetInfoByOfferIBlock($offersIblockId);
            if (is_array($sku) && !empty($sku['PRODUCT_IBLOCK_ID'])) {
                $productsIblockId = (int)$sku['PRODUCT_IBLOCK_ID'];
            }
        }

        // 4) Fallback: ищем SKU-связь через свойство USER_TYPE=SKU (iblock)
        if (($productsIblockId <= 0 || $offersIblockId <= 0) && Loader::includeModule('iblock')) {
            $propRes = \CIBlockProperty::GetList(
                ['ID' => 'ASC'],
                ['ACTIVE' => 'Y', 'PROPERTY_TYPE' => 'E', 'USER_TYPE' => 'SKU']
            );
            if ($prop = $propRes->Fetch()) {
                if ($offersIblockId <= 0) {
                    $offersIblockId = (int)$prop['IBLOCK_ID'];
                }
                if ($productsIblockId <= 0) {
                    $productsIblockId = (int)$prop['LINK_IBLOCK_ID'];
                }
            }
        }

        // 5) Последний fallback — 0 (админ поправит в options.php)
        return [
            max(0, (int)$productsIblockId),
            max(0, (int)$offersIblockId),
        ];
    }
}

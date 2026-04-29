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
        $snippet = $this->buildFrontcalcBasketSnippet();

        if (strpos($content, $startMarker) !== false) {
            $replacePattern = '#<\?php\s*/\*\s*FRONTCALC_BUTTON_START\s*\*/\s*\?>[\s\S]*?<\?php\s*/\*\s*FRONTCALC_BUTTON_END\s*\*/\s*\?>#';
            $replaced = preg_replace($replacePattern, trim($snippet), $content, -1, $replaceCount);

            if (!is_string($replaced) || $replaceCount < 1) {
                throw new \RuntimeException('Не удалось обновить существующий блок FrontCalc в файле: ' . $basketPath);
            }

            if (@file_put_contents($basketPath, $replaced) === false) {
                throw new \RuntimeException('Не удалось записать обновлённый патч в файл: ' . $basketPath);
            }

            return;
        }

        $anchorPattern = '#<\?(?:php)?\s*if\s*\(\s*\$arConfig\[\s*[\'"]SHOW_BASKET_LINK[\'"]\s*\]\s*===\s*[\'"]Y[\'"]\s*\)\s*:\s*\?>#i';
        if (!preg_match_all($anchorPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException(
                'Шаблон Aspro обновился: не найдены подходящие сигнатуры "<?if ($arConfig[\'SHOW_BASKET_LINK\'] === \'Y\'):?>" в файле ' . $basketPath
            );
        }

        $anchorMatches = $matches[0];

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
        return "
<?php /* FRONTCALC_BUTTON_START */ ?>
"
            . "<?php \$frontcalcTemplateIncludeLocal = \$_SERVER['DOCUMENT_ROOT'] . '/local/modules/prospektweb.frontcalc/template_include.php'; ?>
"
            . "<?php \$frontcalcTemplateIncludeBitrix = \$_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/prospektweb.frontcalc/template_include.php'; ?>
"
            . "<?php if (is_file(\$frontcalcTemplateIncludeBitrix)) { require_once \$frontcalcTemplateIncludeBitrix; } elseif (is_file(\$frontcalcTemplateIncludeLocal)) { require_once \$frontcalcTemplateIncludeLocal; } ?>
"
            . "<?php if (function_exists('frontcalc_render_calculate_button')) { echo frontcalc_render_calculate_button((int)(\$arConfig['ITEM_ID'] ?? 0), (int)(\$arConfig['CATALOG_IBLOCK_ID'] ?? 0), 'Рассчитать стоимость', '/local/ajax/frontcalc.php'); } ?>
"
            . "<?php /* FRONTCALC_BUTTON_END */ ?>
";
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

        $ajaxTarget = $_SERVER['DOCUMENT_ROOT'] . '/local/ajax/frontcalc.php';
        $ajaxDir = dirname($ajaxTarget);
        if (!is_dir($ajaxDir) && !@mkdir($ajaxDir, 0775, true) && !is_dir($ajaxDir)) {
            throw new \RuntimeException('Не удалось создать каталог /local/ajax для frontcalc endpoint');
        }

        $ajaxContent = "<?php\n"
            . "\$localPath = \$_SERVER['DOCUMENT_ROOT'] . '/local/modules/" . $this->MODULE_ID . "/ajax/frontcalc.php';\n"
            . "\$bitrixPath = \$_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/" . $this->MODULE_ID . "/ajax/frontcalc.php';\n"
            . "if (is_file(\$localPath)) {\n"
            . "    require_once \$localPath;\n"
            . "} elseif (is_file(\$bitrixPath)) {\n"
            . "    require_once \$bitrixPath;\n"
            . "} else {\n"
            . "    http_response_code(500);\n"
            . "    header('Content-Type: application/json; charset=UTF-8');\n"
            . "    echo json_encode(['success' => false, 'message' => 'frontcalc ajax endpoint not found'], JSON_UNESCAPED_UNICODE);\n"
            . "}\n";

        if (@file_put_contents($ajaxTarget, $ajaxContent) === false) {
            throw new \RuntimeException('Не удалось создать /local/ajax/frontcalc.php');
        }

        $localJsTarget = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/assets/js/frontcalc-jqm-popup.js';
        $localJsDir = dirname($localJsTarget);
        if (!is_dir($localJsDir) && !@mkdir($localJsDir, 0775, true) && !is_dir($localJsDir)) {
            throw new \RuntimeException('Не удалось создать каталог для /local/modules/.../frontcalc-jqm-popup.js');
        }

        $moduleJsSource = dirname(__DIR__) . '/assets/js/frontcalc-jqm-popup.js';
        if (!is_file($moduleJsSource)) {
            throw new \RuntimeException('Не найден исходный файл assets/js/frontcalc-jqm-popup.js');
        }

        if (!@copy($moduleJsSource, $localJsTarget)) {
            throw new \RuntimeException('Не удалось скопировать frontcalc-jqm-popup.js в /local/modules');
        }

        $localTemplateIncludeTarget = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/template_include.php';
        $localTemplateIncludeDir = dirname($localTemplateIncludeTarget);
        if (!is_dir($localTemplateIncludeDir) && !@mkdir($localTemplateIncludeDir, 0775, true) && !is_dir($localTemplateIncludeDir)) {
            throw new \RuntimeException('Не удалось создать каталог для /local/modules/.../template_include.php');
        }

        $moduleTemplateIncludeSource = dirname(__DIR__) . '/template_include.php';
        if (!is_file($moduleTemplateIncludeSource)) {
            throw new \RuntimeException('Не найден исходный файл template_include.php');
        }

        if (!@copy($moduleTemplateIncludeSource, $localTemplateIncludeTarget)) {
            throw new \RuntimeException('Не удалось скопировать template_include.php в /local/modules');
        }

        $moduleRootPath = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID;

        $moduleIncludeSource = dirname(__DIR__) . '/include.php';
        $moduleIncludeTarget = $moduleRootPath . '/include.php';
        if (!is_file($moduleIncludeSource)) {
            throw new \RuntimeException('Не найден исходный файл include.php');
        }
        if (!@copy($moduleIncludeSource, $moduleIncludeTarget)) {
            throw new \RuntimeException('Не удалось скопировать include.php в /local/modules');
        }

        $moduleEditorSource = dirname(__DIR__) . '/admin/editor.php';
        $moduleEditorTarget = $moduleRootPath . '/admin/editor.php';
        $moduleEditorDir = dirname($moduleEditorTarget);
        if (!is_dir($moduleEditorDir) && !@mkdir($moduleEditorDir, 0775, true) && !is_dir($moduleEditorDir)) {
            throw new \RuntimeException('Не удалось создать каталог /local/modules/.../admin');
        }
        if (!is_file($moduleEditorSource)) {
            throw new \RuntimeException('Не найден исходный файл admin/editor.php');
        }
        if (!@copy($moduleEditorSource, $moduleEditorTarget)) {
            throw new \RuntimeException('Не удалось скопировать admin/editor.php в /local/modules');
        }

        $moduleProductCardButtonSource = dirname(__DIR__) . '/lib/Admin/ProductCardButton.php';
        $moduleProductCardButtonTarget = $moduleRootPath . '/lib/Admin/ProductCardButton.php';
        $moduleProductCardButtonDir = dirname($moduleProductCardButtonTarget);
        if (!is_dir($moduleProductCardButtonDir) && !@mkdir($moduleProductCardButtonDir, 0775, true) && !is_dir($moduleProductCardButtonDir)) {
            throw new \RuntimeException('Не удалось создать каталог /local/modules/.../lib/Admin');
        }
        if (!is_file($moduleProductCardButtonSource)) {
            throw new \RuntimeException('Не найден исходный файл lib/Admin/ProductCardButton.php');
        }
        if (!@copy($moduleProductCardButtonSource, $moduleProductCardButtonTarget)) {
            throw new \RuntimeException('Не удалось скопировать lib/Admin/ProductCardButton.php в /local/modules');
        }

        $moduleCalculatorAvailabilitySource = dirname(__DIR__) . '/lib/Service/CalculatorAvailability.php';
        $moduleCalculatorAvailabilityTarget = $moduleRootPath . '/lib/Service/CalculatorAvailability.php';
        $moduleCalculatorAvailabilityDir = dirname($moduleCalculatorAvailabilityTarget);
        if (!is_dir($moduleCalculatorAvailabilityDir) && !@mkdir($moduleCalculatorAvailabilityDir, 0775, true) && !is_dir($moduleCalculatorAvailabilityDir)) {
            throw new \RuntimeException('Не удалось создать каталог /local/modules/.../lib/Service');
        }
        if (!is_file($moduleCalculatorAvailabilitySource)) {
            throw new \RuntimeException('Не найден исходный файл lib/Service/CalculatorAvailability.php');
        }
        if (!@copy($moduleCalculatorAvailabilitySource, $moduleCalculatorAvailabilityTarget)) {
            throw new \RuntimeException('Не удалось скопировать lib/Service/CalculatorAvailability.php в /local/modules');
        }

        return true;
    }

    public function UnInstallFiles()
    {
        $adminTarget = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/prospektweb_frontcalc_editor.php';
        if (is_file($adminTarget)) {
            @unlink($adminTarget);
        }

        $ajaxTarget = $_SERVER['DOCUMENT_ROOT'] . '/local/ajax/frontcalc.php';
        if (is_file($ajaxTarget)) {
            @unlink($ajaxTarget);
        }

        $localJsTarget = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/assets/js/frontcalc-jqm-popup.js';
        if (is_file($localJsTarget)) {
            @unlink($localJsTarget);
        }

        $localTemplateIncludeTarget = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/template_include.php';
        if (is_file($localTemplateIncludeTarget)) {
            @unlink($localTemplateIncludeTarget);
        }

        $moduleRootPath = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID;
        $moduleIncludeTarget = $moduleRootPath . '/include.php';
        if (is_file($moduleIncludeTarget)) {
            @unlink($moduleIncludeTarget);
        }

        $moduleEditorTarget = $moduleRootPath . '/admin/editor.php';
        if (is_file($moduleEditorTarget)) {
            @unlink($moduleEditorTarget);
        }

        $moduleProductCardButtonTarget = $moduleRootPath . '/lib/Admin/ProductCardButton.php';
        if (is_file($moduleProductCardButtonTarget)) {
            @unlink($moduleProductCardButtonTarget);
        }

        $moduleCalculatorAvailabilityTarget = $moduleRootPath . '/lib/Service/CalculatorAvailability.php';
        if (is_file($moduleCalculatorAvailabilityTarget)) {
            @unlink($moduleCalculatorAvailabilityTarget);
        }

        @rmdir($moduleRootPath . '/admin');
        @rmdir($moduleRootPath . '/lib/Admin');
        @rmdir($moduleRootPath . '/lib/Service');
        @rmdir($moduleRootPath . '/lib');

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

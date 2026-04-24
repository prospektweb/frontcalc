<?php

use Bitrix\Main\Config\Option;
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
            $this->InstallDB();
        } catch (\Throwable $e) {
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

        // Пример "сущности модуля", чтобы показать удаление/сохранение при uninstall
        Option::set($this->MODULE_ID, 'EXAMPLE_ENTITY_CREATED', 'Y');

        return true;
    }

    public function UnInstallDB($removeData = false)
    {
        if ($removeData) {
            Option::delete($this->MODULE_ID);
        } else {
            // Служебно помечаем, что uninstall прошёл с сохранением данных
            Option::set($this->MODULE_ID, 'UNINSTALLED_AT', date('c'));
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

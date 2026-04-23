<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class prospektweb_frontcalc extends CModule
{
    public const DEFAULT_PROPERTY_CODE = 'CALC_CONFIG';
    public const TARGET_PRICES_FILE = '/bitrix/modules/aspro.premier/lib/product/prices.php';
    public const TARGET_PRICES_BACKUP_FILE = '/bitrix/modules/aspro.premier/lib/product/prices_original.php';

    public $MODULE_ID = 'prospektweb.frontcalc';
    public $MODULE_NAME = 'Калькулятор себестоимости';
    public $MODULE_DESCRIPTION = 'Калькулятор и модификатор цен для Aspro Premier';
    public $MODULE_VERSION = '1.0.1';
    public $MODULE_VERSION_DATE = '2026-04-23';
    public $PARTNER_NAME = 'PROSPEKT-WEB';

    public function __construct()
    {
        $versionFile = __DIR__ . '/version.php';
        if (is_file($versionFile)) {
            include $versionFile;
            if (is_array($arModuleVersion)) {
                $this->MODULE_VERSION = $arModuleVersion['VERSION'];
                $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
            }
        }
    }

    public function DoInstall(): void
    {
        global $APPLICATION;

        if (!$this->checkDependencies()) {
            return;
        }

        RegisterModule($this->MODULE_ID);
        $this->InstallDB();

        $APPLICATION->IncludeAdminFile('Модуль установлен', __DIR__ . '/step.php');
    }

    public function DoUninstall(): void
    {
        global $APPLICATION;

        $request = Application::getInstance()->getContext()->getRequest();
        $step = (int)$request->get('step');

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile('Удаление модуля', __DIR__ . '/unstep1.php');
            return;
        }

        $this->UnInstallDB([
            'delete_calc_property' => $request->getPost('delete_calc_property') === 'Y',
        ]);

        UnRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile('Модуль удален', __DIR__ . '/unstep2.php');
    }

    public function InstallDB(): bool
    {
        [$productsIblockId, $offersIblockId] = $this->resolveCatalogIblocks();

        Option::set($this->MODULE_ID, 'PRODUCTS_IBLOCK_ID', (string)$productsIblockId);
        Option::set($this->MODULE_ID, 'OFFERS_IBLOCK_ID', (string)$offersIblockId);

        if ($productsIblockId > 0) {
            $this->createCalcConfigProperty($productsIblockId);
        }

        $this->replacePricesFile();

        return true;
    }

    public function UnInstallDB(array $params = []): bool
    {
        $deleteProperty = !empty($params['delete_calc_property']);
        $productsIblockId = (int)Option::get($this->MODULE_ID, 'PRODUCTS_IBLOCK_ID', '0');

        if ($deleteProperty && $productsIblockId > 0 && Loader::includeModule('iblock')) {
            $property = CIBlockProperty::GetList(
                [],
                [
                    'IBLOCK_ID' => $productsIblockId,
                    'CODE' => self::DEFAULT_PROPERTY_CODE,
                ]
            )->Fetch();

            if ($property && isset($property['ID'])) {
                CIBlockProperty::Delete((int)$property['ID']);
            }
        }

        Option::delete($this->MODULE_ID, ['name' => 'PRODUCTS_IBLOCK_ID']);
        Option::delete($this->MODULE_ID, ['name' => 'OFFERS_IBLOCK_ID']);

        $this->restorePricesFile();

        return true;
    }

    protected function checkDependencies(): bool
    {
        global $APPLICATION;

        $requiredModules = ['iblock', 'catalog', 'sale'];
        foreach ($requiredModules as $moduleId) {
            if (!Loader::includeModule($moduleId)) {
                $APPLICATION->ThrowException(sprintf('Не установлен обязательный модуль: %s', $moduleId));
                return false;
            }
        }

        return true;
    }

    protected function resolveCatalogIblocks(): array
    {
        $productsIblockId = 0;
        $offersIblockId = 0;

        if (!Loader::includeModule('catalog')) {
            return [$productsIblockId, $offersIblockId];
        }

        $catalogRows = \Bitrix\Catalog\CatalogIblockTable::getList([
            'select' => ['IBLOCK_ID', 'PRODUCT_IBLOCK_ID'],
            'order' => ['IBLOCK_ID' => 'ASC'],
        ]);

        while ($row = $catalogRows->fetch()) {
            $iblockId = (int)$row['IBLOCK_ID'];
            $productIblockId = (int)$row['PRODUCT_IBLOCK_ID'];

            if ($productIblockId > 0) {
                $offersIblockId = $offersIblockId ?: $iblockId;
                $productsIblockId = $productsIblockId ?: $productIblockId;
                continue;
            }

            $productsIblockId = $productsIblockId ?: $iblockId;
        }

        return [$productsIblockId, $offersIblockId];
    }

    protected function createCalcConfigProperty(int $iblockId): void
    {
        if (!Loader::includeModule('iblock')) {
            return;
        }

        $exists = CIBlockProperty::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockId,
                'CODE' => self::DEFAULT_PROPERTY_CODE,
            ]
        )->Fetch();

        if ($exists) {
            return;
        }

        $property = new CIBlockProperty();
        $property->Add([
            'IBLOCK_ID' => $iblockId,
            'NAME' => 'Конфиг калькулятора',
            'ACTIVE' => 'Y',
            'SORT' => 500,
            'CODE' => self::DEFAULT_PROPERTY_CODE,
            'PROPERTY_TYPE' => 'S',
            'USER_TYPE' => 'HTML',
            'MULTIPLE' => 'N',
            'IS_REQUIRED' => 'N',
        ]);
    }

    protected function replacePricesFile(): void
    {
        $source = dirname(__DIR__) . '/source/prices.php';
        $target = $_SERVER['DOCUMENT_ROOT'] . self::TARGET_PRICES_FILE;
        $backup = $_SERVER['DOCUMENT_ROOT'] . self::TARGET_PRICES_BACKUP_FILE;

        if (!is_file($source) || !is_file($target)) {
            return;
        }

        if (!is_file($backup)) {
            @rename($target, $backup);
        } else {
            @unlink($target);
        }

        @copy($source, $target);
    }

    protected function restorePricesFile(): void
    {
        $target = $_SERVER['DOCUMENT_ROOT'] . self::TARGET_PRICES_FILE;
        $backup = $_SERVER['DOCUMENT_ROOT'] . self::TARGET_PRICES_BACKUP_FILE;

        if (!is_file($backup)) {
            return;
        }

        if (is_file($target)) {
            @unlink($target);
        }

        @rename($backup, $target);
    }
}


// Backward compatibility aliases for old installer class names.
class prospektweb_calc extends prospektweb_frontcalc {}
class front_calculator extends prospektweb_frontcalc {}

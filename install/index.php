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
            $this->frontcalcInstallPatchCatalogElementTemplate();
            $this->frontcalcInstallReplaceAsproPricesTemplate();
        } catch (\Throwable $e) {
            $this->frontcalcInstallRestoreAsproPricesTemplate();
            $this->frontcalcInstallRemoveCatalogElementSnippet();
            $this->frontcalcInstallRemoveBasketSnippet();
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

        $this->frontcalcInstallRestoreAsproPricesTemplate();
        $this->frontcalcInstallRemoveCatalogElementSnippet();
        $this->frontcalcInstallRemoveBasketSnippet();
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


    protected function getAsproPricesPath()
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/aspro.premier/lib/product/prices.php';
    }

    protected function getAsproPricesSourcePath()
    {
        $localPath = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/prices_with_edit.php';
        if (is_file($localPath) && is_readable($localPath)) {
            return $localPath;
        }

        return dirname(__DIR__) . '/prices_with_edit.php';
    }

    protected function getAsproPricesBackupDir()
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/backup/aspro_premier';
    }

    protected function patchAsproPricesFile()
    {
        $targetPath = $this->getAsproPricesPath();
        if (!is_file($targetPath)) {
            throw new \RuntimeException('Не найден файл Aspro prices.php для патча: ' . $targetPath);
        }
        if (!is_readable($targetPath)) {
            throw new \RuntimeException('Файл Aspro prices.php не читается: ' . $targetPath);
        }

        $sourcePath = $this->getAsproPricesSourcePath();
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new \RuntimeException('Не найден или не читается исходный файл prices_with_edit.php: ' . $sourcePath);
        }

        $backupDir = $this->getAsproPricesBackupDir();
        if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            throw new \RuntimeException('Не удалось создать каталог backup для Aspro prices.php: ' . $backupDir);
        }

        $originalHash = hash_file('sha256', $targetPath);
        if (!is_string($originalHash) || $originalHash === '') {
            throw new \RuntimeException('Не удалось посчитать SHA-256 оригинального Aspro prices.php: ' . $targetPath);
        }

        $backupPath = $backupDir . '/prices.php.' . date('YmdHis') . '.bak';
        if (!@copy($targetPath, $backupPath)) {
            throw new \RuntimeException('Не удалось сохранить backup Aspro prices.php: ' . $backupPath);
        }

        $sourceContent = file_get_contents($sourcePath);
        if ($sourceContent === false || $sourceContent === '') {
            throw new \RuntimeException('Не удалось прочитать исходный файл prices_with_edit.php: ' . $sourcePath);
        }

        if (@file_put_contents($targetPath, $sourceContent) === false) {
            throw new \RuntimeException('Не удалось заменить Aspro prices.php файлом prices_with_edit.php: ' . $targetPath);
        }

        $patchedHash = hash_file('sha256', $targetPath);
        if (!is_string($patchedHash) || $patchedHash === '') {
            throw new \RuntimeException('Не удалось посчитать SHA-256 установленного Aspro prices.php: ' . $targetPath);
        }

        Option::set($this->MODULE_ID, 'ASPRO_PRICES_PATH', $targetPath);
        Option::set($this->MODULE_ID, 'ASPRO_PRICES_BACKUP_PATH', $backupPath);
        Option::set($this->MODULE_ID, 'ASPRO_PRICES_ORIGINAL_HASH', $originalHash);
        Option::set($this->MODULE_ID, 'ASPRO_PRICES_PATCHED_HASH', $patchedHash);
    }

    protected function restoreAsproPricesFile()
    {
        $targetPath = (string)Option::get($this->MODULE_ID, 'ASPRO_PRICES_PATH', $this->getAsproPricesPath());
        $backupPath = (string)Option::get($this->MODULE_ID, 'ASPRO_PRICES_BACKUP_PATH', '');
        $patchedHash = (string)Option::get($this->MODULE_ID, 'ASPRO_PRICES_PATCHED_HASH', '');

        if ($backupPath === '' || !is_file($backupPath) || !is_readable($backupPath)) {
            return $this->addAsproPricesRestoreWarning(
                'Backup Aspro prices.php отсутствует или не читается, восстановление пропущено: ' . ($backupPath ?: 'путь не сохранён')
            );
        }

        if (!is_file($targetPath)) {
            return $this->restoreAsproPricesFromBackup($backupPath, $targetPath);
        }

        if (!is_readable($targetPath)) {
            return $this->addAsproPricesRestoreWarning('Текущий Aspro prices.php не читается, восстановление пропущено: ' . $targetPath);
        }

        if ($patchedHash === '') {
            return $this->addAsproPricesRestoreWarning('Не сохранён SHA-256 установленного Aspro prices.php, восстановление пропущено: ' . $targetPath);
        }

        $currentHash = hash_file('sha256', $targetPath);
        if (!is_string($currentHash) || $currentHash === '') {
            return $this->addAsproPricesRestoreWarning('Не удалось посчитать SHA-256 текущего Aspro prices.php, восстановление пропущено: ' . $targetPath);
        }

        if (hash_equals($patchedHash, $currentHash)) {
            return $this->restoreAsproPricesFromBackup($backupPath, $targetPath);
        }

        return $this->addAsproPricesRestoreWarning(
            'Aspro prices.php был изменён после установки модуля, backup не восстановлен: ' . $targetPath
        );
    }

    protected function restoreAsproPricesFromBackup($backupPath, $targetPath)
    {
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return $this->addAsproPricesRestoreWarning('Не удалось создать каталог для восстановления Aspro prices.php: ' . $targetDir);
        }

        if (!@copy($backupPath, $targetPath)) {
            return $this->addAsproPricesRestoreWarning('Не удалось восстановить Aspro prices.php из backup: ' . $backupPath);
        }

        Option::set($this->MODULE_ID, 'ASPRO_PRICES_RESTORED_AT', date('c'));

        return '';
    }

    protected function addAsproPricesRestoreWarning($message)
    {
        Option::set($this->MODULE_ID, 'ASPRO_PRICES_RESTORE_WARNING', $message);

        if (function_exists('AddMessage2Log')) {
            AddMessage2Log($message, $this->MODULE_ID);
        }

        return $message;
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
            $replacePattern = '#(?:<\?php\s*)?/\*\s*FRONTCALC_BUTTON_START\s*\*/(?:\s*\?>)?[\s\S]*?(?:<\?php\s*)?/\*\s*FRONTCALC_BUTTON_END\s*\*/(?:\s*\?>)?#';
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

    protected function unpatchAsproBasketFile()
    {
        $basketPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/aspro.premier/lib/product/basket.php';
        if (!is_file($basketPath)) {
            return $this->addAsproBasketRestoreWarning('Не найден файл Aspro basket.php для удаления патча: ' . $basketPath);
        }

        if (!is_readable($basketPath)) {
            return $this->addAsproBasketRestoreWarning('Файл Aspro basket.php не читается, удаление патча пропущено: ' . $basketPath);
        }

        $content = file_get_contents($basketPath);
        if (!is_string($content) || $content === '') {
            return $this->addAsproBasketRestoreWarning('Файл Aspro basket.php пуст или не читается, удаление патча пропущено: ' . $basketPath);
        }

        $startMarker = '/* FRONTCALC_BUTTON_START */';
        $endMarker = '/* FRONTCALC_BUTTON_END */';
        if (strpos($content, $startMarker) === false || strpos($content, $endMarker) === false) {
            return $this->addAsproBasketRestoreWarning(
                'Маркеры FrontCalc не найдены в Aspro basket.php, удаление патча пропущено: ' . $basketPath
            );
        }

        $replacePattern = '#(?:<\?php\s*)?/\*\s*FRONTCALC_BUTTON_START\s*\*/(?:\s*\?>)?[\s\S]*?(?:<\?php\s*)?/\*\s*FRONTCALC_BUTTON_END\s*\*/(?:\s*\?>)?#';
        $unpatchedContent = preg_replace($replacePattern, '', $content, -1, $replaceCount);
        if (!is_string($unpatchedContent) || $replaceCount < 1) {
            return $this->addAsproBasketRestoreWarning(
                'Не удалось удалить блок FrontCalc по маркерам из Aspro basket.php: ' . $basketPath
            );
        }

        if (@file_put_contents($basketPath, $unpatchedContent) === false) {
            return $this->addAsproBasketRestoreWarning('Не удалось записать Aspro basket.php после удаления патча: ' . $basketPath);
        }

        Option::set($this->MODULE_ID, 'ASPRO_BASKET_UNPATCHED_AT', date('c'));

        return '';
    }

    protected function addAsproBasketRestoreWarning($message)
    {
        Option::set($this->MODULE_ID, 'ASPRO_BASKET_RESTORE_WARNING', $message);

        if (function_exists('AddMessage2Log')) {
            AddMessage2Log($message, $this->MODULE_ID);
        }

        return $message;
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
            . "<?php if (function_exists('frontcalc_render_catalog_button')) { echo frontcalc_render_catalog_button((int)(\$arConfig['ITEM_ID'] ?? 0), (int)(\$arConfig['CATALOG_IBLOCK_ID'] ?? 0), '/local/ajax/frontcalc.php'); } ?>
"
            . "<?php /* FRONTCALC_BUTTON_END */ ?>
";
    }


    protected function frontcalcInstallGetCatalogElementTemplatePath(): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/bitrix/templates/aspro-premier/components/bitrix/catalog.element/main/template.php';
    }

    protected function frontcalcInstallPatchCatalogElementTemplate(): void
    {
        $templatePath = $this->frontcalcInstallGetCatalogElementTemplatePath();
        if (!is_file($templatePath)) {
            throw new \RuntimeException('Не найден файл шаблона catalog.element для патча FrontCalc: ' . $templatePath);
        }

        $content = (string)file_get_contents($templatePath);
        if ($content === '') {
            throw new \RuntimeException('Файл шаблона catalog.element пуст или не читается: ' . $templatePath);
        }

        $this->frontcalcInstallRemoveCatalogElementSnippetFromContent($content);
        $this->frontcalcInstallInsertCatalogElementFlags($content, $templatePath);
        $this->frontcalcInstallGuardCatalogElementSchema($content, $templatePath);
        $this->frontcalcInstallInsertCatalogElementInlineRenderer($content, $templatePath);
        $this->frontcalcInstallWrapCatalogElementStandardBlocks($content, $templatePath);
        $this->frontcalcInstallEnsureCatalogElementInfoBlocksVisible($content);

        if (@file_put_contents($templatePath, $content) === false) {
            throw new \RuntimeException('Не удалось записать FrontCalc-патч в файл шаблона catalog.element: ' . $templatePath);
        }
    }

    protected function frontcalcInstallInsertCatalogElementFlags(string &$content, string $templatePath): void
    {
        $anchorPositions = [];
        foreach ($this->frontcalcInstallGetCatalogElementTopAnchors() as $anchor) {
            $position = strpos($content, $anchor);
            if ($position !== false) {
                $anchorPositions[] = $position;
            }
        }

        if ($anchorPositions === []) {
            throw new \RuntimeException('Шаблон Aspro обновился: не найдены верхние блоки catalog.element для вставки FrontCalc-флагов: ' . $templatePath);
        }

        $insertPosition = min($anchorPositions);
        $lineStartPosition = strrpos(substr($content, 0, $insertPosition), "\n");
        if ($lineStartPosition !== false) {
            $insertPosition = $lineStartPosition + 1;
        }

        $content = substr($content, 0, $insertPosition)
            . $this->frontcalcInstallBuildCatalogElementFlagsSnippet()
            . substr($content, $insertPosition);
    }

    protected function frontcalcInstallInsertCatalogElementInlineRenderer(string &$content, string $templatePath): void
    {
        $startMarker = '/* FRONTCALC_INLINE_RENDER_START */';
        if (strpos($content, $startMarker) !== false) {
            return;
        }

        $anchorPositions = [];
        foreach ($this->frontcalcInstallGetCatalogElementTopAnchors() as $anchor) {
            $position = strpos($content, $anchor);
            if ($position !== false) {
                $anchorPositions[] = $position;
            }
        }

        if ($anchorPositions === []) {
            throw new \RuntimeException('Шаблон Aspro обновился: не найдены верхние блоки catalog.element для вывода inline-калькулятора: ' . $templatePath);
        }

        $insertPosition = min($anchorPositions);
        $lineStartPosition = strrpos(substr($content, 0, $insertPosition), "\n");
        if ($lineStartPosition !== false) {
            $insertPosition = $lineStartPosition + 1;
        }

        $content = substr($content, 0, $insertPosition)
            . $this->frontcalcInstallBuildCatalogElementInlineRendererSnippet()
            . substr($content, $insertPosition);
    }

    protected function frontcalcInstallGetCatalogElementTopAnchors(): array
    {
        return [
            'h1.switcher-title',
            'switcher-title',
            'js-replace-article',
            '/* sku replace start */',
            'TSolution\\SKU\\Template::showSkuPropsHtml',
            'catalog-detail__offers',
            'TSolution\\Product\\Prices',
            '$prices->show',
            'TSolution\\Product\\Basket::getOptions',
            'catalog-detail__buy-block',
        ];
    }

    protected function frontcalcInstallBuildCatalogElementFlagsSnippet(): string
    {
        return <<<'PHP'
<?php /* FRONTCALC_FLAGS_START */ ?>
<?php
$frontcalcTemplateInclude = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/prospektweb.frontcalc/template_include.php';
if (!is_file($frontcalcTemplateInclude)) {
    $frontcalcTemplateInclude = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/prospektweb.frontcalc/template_include.php';
}
if (is_file($frontcalcTemplateInclude)) {
    require_once $frontcalcTemplateInclude;
}

$frontcalcCanUse = function_exists('frontcalc_can_use_config_from_result')
    ? frontcalc_can_use_config_from_result($arResult)
    : false;
$frontcalcIsAuthorized = is_object($USER) && $USER->IsAuthorized();
$frontcalcUseInline = $frontcalcCanUse && $frontcalcIsAuthorized;
$frontcalcShowAuthButton = $frontcalcCanUse && !$frontcalcIsAuthorized;
?>
<?php /* FRONTCALC_FLAGS_END */ ?>
PHP . "\n";
    }

    protected function frontcalcInstallBuildCatalogElementInlineRendererSnippet(): string
    {
        return <<<'PHP'
<?php /* FRONTCALC_INLINE_RENDER_START */ ?>
<?php if ($frontcalcUseInline === true && function_exists('frontcalc_render_detail_inline')): ?>
    <?=frontcalc_render_detail_inline((int)$arResult['ID'], (int)$arResult['IBLOCK_ID'], '/local/ajax/frontcalc.php');?>
<?php endif; ?>
<?php /* FRONTCALC_INLINE_RENDER_END */ ?>
PHP . "\n";
    }

    protected function frontcalcInstallGuardCatalogElementSchema(string &$content, string $templatePath): void
    {
        $condition = $this->frontcalcInstallFindCatalogElementSchemaCondition($content);
        if ($condition === null) {
            throw new \RuntimeException('Шаблон Aspro обновился: не найден блок schema.org Product с prices в catalog.element: ' . $templatePath);
        }

        [$conditionText, $conditionPosition] = $condition;
        if (strpos($conditionText, '$frontcalcUseInline') !== false) {
            return;
        }

        $content = substr($content, 0, $conditionPosition)
            . 'if ($bUseSchema && !$frontcalcUseInline) {'
            . substr($content, $conditionPosition + strlen($conditionText));
    }

    protected function frontcalcInstallRestoreCatalogElementSchemaGuard(string &$content): void
    {
        $condition = $this->frontcalcInstallFindCatalogElementSchemaCondition($content);
        if ($condition === null) {
            return;
        }

        [$conditionText, $conditionPosition] = $condition;
        if (strpos($conditionText, '$frontcalcUseInline') === false) {
            return;
        }

        $content = substr($content, 0, $conditionPosition)
            . 'if ($bUseSchema) {'
            . substr($content, $conditionPosition + strlen($conditionText));
    }

    protected function frontcalcInstallFindCatalogElementSchemaCondition(string $content): ?array
    {
        $schemaPattern = '#new\s+TSolution\\Scheme\\Product\s*\([\s\S]*?prices\s*:\s*\$prices#';
        if (!preg_match($schemaPattern, $content, $schemaMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $schemaPosition = (int)$schemaMatch[0][1];
        $prefix = substr($content, 0, $schemaPosition);
        $conditionPattern = '#if\s*\(\s*\$bUseSchema(?:\s*&&\s*!\s*\$frontcalcUseInline)?\s*\)\s*\{#';
        if (!preg_match_all($conditionPattern, $prefix, $conditionMatches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $lastIndex = count($conditionMatches[0]) - 1;

        return [
            $conditionMatches[0][$lastIndex][0],
            (int)$conditionMatches[0][$lastIndex][1],
        ];
    }

    protected function frontcalcInstallEnsureCatalogElementInfoBlocksVisible(string &$content): void
    {
        $skipPattern = '#<\?php\s*/\*\s*FRONTCALC_INLINE_SKIP_([A-Z0-9_]+)_START\s*\*/\s*\?>\s*<\?php\s*if\s*\(\s*\$frontcalcUseInline\s*(?:!==\s*true|===\s*false)\s*\)\s*:\s*\?>\s*([\s\S]*?)\s*<\?php\s*endif;\s*\?>\s*<\?php\s*/\*\s*FRONTCALC_INLINE_SKIP_\1_END\s*\*/\s*\?>#';
        $updated = preg_replace_callback(
            $skipPattern,
            function (array $matches): string {
                $block = $matches[2];
                if (!$this->frontcalcInstallCatalogElementBlockHasAlwaysVisibleInfo($block)) {
                    return $matches[0];
                }

                return "\n" . $block . "\n";
            },
            $content
        );

        if (is_string($updated)) {
            $content = $updated;
        }
    }

    protected function frontcalcInstallCatalogElementBlockHasAlwaysVisibleInfo(string $block): bool
    {
        foreach ($this->frontcalcInstallGetCatalogElementAlwaysVisibleInfoAnchors() as $anchor) {
            if (strpos($block, $anchor) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function frontcalcInstallGetCatalogElementAlwaysVisibleInfoAnchors(): array
    {
        return [
            'catalog-detail__previewtext',
            '/catalog/props_in_section.php',
            'PRODUCT_PROPS_INFO',
            'PRODUCT_DETAIL_TEXT_INFO',
        ];
    }

    protected function frontcalcInstallWrapCatalogElementStandardBlocks(string &$content, string $templatePath): void
    {
        $patterns = [
            'TITLE' => '#<h1\b(?=[^>]*\bswitcher-title\b)[\s\S]*?</h1>#i',
            'ARTICLE' => '#<(?P<tag>div|span|p)\b(?=[^>]*\bjs-replace-article\b)[\s\S]*?</(?P=tag)>#i',
            'SKU_PROPS' => '#<\?(?:php)?\s*TSolution\\\\SKU\\\\Template::showSkuPropsHtml\s*\([\s\S]*?\);\s*\?>#',
            'PRICE_OBJECT' => '#<\?(?:php)?\s*\$prices\s*=\s*new\s+TSolution\\\\Product\\\\Prices\s*\([\s\S]*?\);\s*\?>#',
            'PRICE_SHOW' => '#<\?(?:php)?\s*\$prices->show\s*\([\s\S]*?\);\s*\?>#',
            'BASKET_OPTIONS' => '#<\?(?:php)?\s*\$btnHtml\s*=\s*TSolution\\\\Product\\\\Basket::getOptions\s*\([\s\S]*?\);\s*\?>#',
        ];

        $matchedLabels = [];
        if ($this->frontcalcInstallWrapCatalogElementDivByClass($content, 'catalog-detail__offers', 'OFFERS')) {
            $matchedLabels[] = 'OFFERS';
        }
        if ($this->frontcalcInstallWrapCatalogElementDivByClass($content, 'catalog-detail__buy-block', 'BUY_BLOCK')) {
            $matchedLabels[] = 'BUY_BLOCK';
        }

        foreach ($patterns as $label => $pattern) {
            $marker = '/* FRONTCALC_INLINE_SKIP_' . $label . '_START */';
            if (strpos($content, $marker) !== false) {
                $matchedLabels[] = $label;
                continue;
            }

            $content = preg_replace_callback(
                $pattern,
                function (array $matches) use ($label): string {
                    if ($label === 'BASKET_OPTIONS') {
                        return $this->frontcalcInstallWrapCatalogElementBasketOptionsBlock($matches[0]);
                    }

                    return $this->frontcalcInstallWrapCatalogElementStandardBlock($matches[0], $label);
                },
                $content,
                1,
                $replaceCount
            );

            if (!is_string($content)) {
                throw new \RuntimeException('Не удалось обернуть блок ' . $label . ' в шаблоне catalog.element');
            }
            if ($replaceCount > 0) {
                $matchedLabels[] = $label;
            }
        }

        $requiredLabels = ['TITLE', 'ARTICLE', 'PRICE_OBJECT', 'PRICE_SHOW', 'OFFERS', 'SKU_PROPS', 'BASKET_OPTIONS', 'BUY_BLOCK'];
        $missedLabels = array_values(array_diff($requiredLabels, array_unique($matchedLabels)));
        if ($missedLabels !== []) {
            throw new \RuntimeException(
                'Шаблон Aspro обновился: не найдены стандартные блоки catalog.element для FrontCalc-обёртки ('
                . implode(', ', $missedLabels)
                . '): '
                . $templatePath
            );
        }
    }

    protected function frontcalcInstallWrapCatalogElementStandardBlock(string $block, string $label): string
    {
        return '<?php /* FRONTCALC_INLINE_SKIP_' . $label . '_START */ ?>' . "\n"
            . '<?php if ($frontcalcUseInline === false): ?>' . "\n"
            . $block . "\n"
            . '<?php endif; ?>' . "\n"
            . '<?php /* FRONTCALC_INLINE_SKIP_' . $label . '_END */ ?>';
    }

    protected function frontcalcInstallWrapCatalogElementBasketOptionsBlock(string $block): string
    {
        return '<?php /* FRONTCALC_INLINE_SKIP_BASKET_OPTIONS_START */ ?>' . "\n"
            . '<?php if ($frontcalcUseInline === false): ?>' . "\n"
            . $block . "\n"
            . '<?php /* FRONTCALC_AUTH_BUTTON_START */ ?>' . "\n"
            . "<?php if (\$frontcalcShowAuthButton === true && function_exists('frontcalc_render_auth_required_button')) { \$btnHtml .= frontcalc_render_auth_required_button('Рассчитать стоимость', 'btn-elg btn-wide'); } ?>" . "\n"
            . '<?php /* FRONTCALC_AUTH_BUTTON_END */ ?>' . "\n"
            . '<?php endif; ?>' . "\n"
            . '<?php /* FRONTCALC_INLINE_SKIP_BASKET_OPTIONS_END */ ?>';
    }

    protected function frontcalcInstallWrapCatalogElementDivByClass(string &$content, string $className, string $label): bool
    {
        $marker = '/* FRONTCALC_INLINE_SKIP_' . $label . '_START */';
        if (strpos($content, $marker) !== false) {
            return true;
        }

        $classPattern = preg_quote($className, '#');
        if (!preg_match('#<div\b(?=[^>]*\b' . $classPattern . '\b)[^>]*>#i', $content, $openingMatch, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $blockStart = (int)$openingMatch[0][1];
        $scanStart = $blockStart + strlen($openingMatch[0][0]);
        $scanContent = substr($content, $scanStart);
        if (!preg_match_all('#</?div\b[^>]*>#i', $scanContent, $tagMatches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $depth = 1;
        foreach ($tagMatches[0] as $tagMatch) {
            $tag = $tagMatch[0];
            $tagOffset = $scanStart + (int)$tagMatch[1];
            if (stripos($tag, '</div') === 0) {
                $depth--;
                if ($depth === 0) {
                    $blockEnd = $tagOffset + strlen($tag);
                    $block = substr($content, $blockStart, $blockEnd - $blockStart);
                    $content = substr($content, 0, $blockStart)
                        . $this->frontcalcInstallWrapCatalogElementStandardBlock($block, $label)
                        . substr($content, $blockEnd);

                    return true;
                }
            } else {
                $depth++;
            }
        }

        return false;
    }

    protected function frontcalcInstallRemoveCatalogElementSnippet(): void
    {
        $templatePath = $this->frontcalcInstallGetCatalogElementTemplatePath();
        if (!is_file($templatePath)) {
            return;
        }

        $content = (string)file_get_contents($templatePath);
        if ($content === '') {
            return;
        }

        $updated = $content;
        $this->frontcalcInstallRemoveCatalogElementSnippetFromContent($updated);
        if ($updated === $content) {
            return;
        }

        if (@file_put_contents($templatePath, $updated) === false) {
            $this->frontcalcInstallLogWarning('Не удалось удалить FrontCalc-патч из файла: ' . $templatePath);
        }
    }

    protected function frontcalcInstallRemoveCatalogElementSnippetFromContent(string &$content): void
    {
        $this->frontcalcInstallRestoreCatalogElementSchemaGuard($content);

        $skipPattern = '#\s*<\?php\s*/\*\s*FRONTCALC_INLINE_SKIP_([A-Z0-9_]+)_START\s*\*/\s*\?>\s*<\?php\s*if\s*\(\s*\$frontcalcUseInline\s*(?:!==\s*true|===\s*false)\s*\)\s*:\s*\?>\s*([\s\S]*?)\s*<\?php\s*endif;\s*\?>\s*<\?php\s*/\*\s*FRONTCALC_INLINE_SKIP_\1_END\s*\*/\s*\?>#';
        $unwrapped = preg_replace($skipPattern, "\n$2", $content);
        if (is_string($unwrapped)) {
            $content = $unwrapped;
        }

        $removePatterns = [
            '#\s*<\?php\s*/\*\s*FRONTCALC_AUTH_BUTTON_START\s*\*/\s*\?>[\s\S]*?<\?php\s*/\*\s*FRONTCALC_AUTH_BUTTON_END\s*\*/\s*\?>\s*#',
            '#\s*<\?php\s*/\*\s*FRONTCALC_INLINE_RENDER_START\s*\*/\s*\?>[\s\S]*?<\?php\s*/\*\s*FRONTCALC_INLINE_RENDER_END\s*\*/\s*\?>\s*#',
            '#\s*<\?php\s*/\*\s*FRONTCALC_FLAGS_START\s*\*/\s*\?>[\s\S]*?<\?php\s*/\*\s*FRONTCALC_FLAGS_END\s*\*/\s*\?>\s*#',
        ];

        foreach ($removePatterns as $pattern) {
            $cleaned = preg_replace($pattern, "\n", $content);
            if (is_string($cleaned)) {
                $content = $cleaned;
            }
        }
    }

    protected function frontcalcInstallRemoveBasketSnippet(): void
    {
        $basketPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/aspro.premier/lib/product/basket.php';
        if (!is_file($basketPath)) {
            return;
        }

        $content = (string)file_get_contents($basketPath);
        if ($content === '') {
            return;
        }

        $pattern = '#\s*<\?php\s*/\*\s*FRONTCALC_BUTTON_START\s*\*/\s*\?>[\s\S]*?<\?php\s*/\*\s*FRONTCALC_BUTTON_END\s*\*/\s*\?>\s*#';
        $updated = preg_replace($pattern, "\n", $content, -1, $replaceCount);
        if (!is_string($updated) || $replaceCount < 1) {
            return;
        }

        if (@file_put_contents($basketPath, $updated) === false) {
            $this->frontcalcInstallLogWarning('Не удалось удалить патч FrontCalc из файла: ' . $basketPath);
        }
    }

    protected function frontcalcInstallReplaceAsproPricesTemplate(): void
    {
        $targetPath = $this->frontcalcInstallGetAsproPricesPath();
        $sourcePath = dirname(__DIR__) . '/prices_with_edit.php';

        if (!is_file($targetPath)) {
            throw new \RuntimeException('Не найден файл Aspro для замены: ' . $targetPath);
        }
        if (!is_readable($targetPath) || !is_writable($targetPath)) {
            throw new \RuntimeException('Файл Aspro prices.php недоступен для чтения или записи: ' . $targetPath);
        }
        if (!is_file($sourcePath)) {
            throw new \RuntimeException('Не найден исходный файл prices_with_edit.php');
        }

        $patchedContent = (string)file_get_contents($sourcePath);
        if ($patchedContent === '') {
            throw new \RuntimeException('Файл prices_with_edit.php пуст или не читается');
        }

        $patchedSourceHash = hash('sha256', $patchedContent);
        $currentHash = hash_file('sha256', $targetPath);
        if ($currentHash === $patchedSourceHash) {
            Option::set($this->MODULE_ID, 'ASPRO_PRICES_PATH', $targetPath);
            Option::set($this->MODULE_ID, 'ASPRO_PRICES_PATCHED_HASH', $patchedSourceHash);
            return;
        }

        $backupDir = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/backup/aspro_premier';
        if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            throw new \RuntimeException('Не удалось создать каталог резервных копий: ' . $backupDir);
        }

        $backupPath = $backupDir . '/prices.php.' . date('YmdHis') . '.bak';
        if (!@copy($targetPath, $backupPath)) {
            throw new \RuntimeException('Не удалось создать резервную копию prices.php: ' . $backupPath);
        }

        if (@file_put_contents($targetPath, $patchedContent) === false) {
            throw new \RuntimeException('Не удалось заменить файл Aspro prices.php: ' . $targetPath);
        }

        Option::set($this->MODULE_ID, 'ASPRO_PRICES_PATH', $targetPath);
        Option::set($this->MODULE_ID, 'ASPRO_PRICES_BACKUP_PATH', $backupPath);
        Option::set($this->MODULE_ID, 'ASPRO_PRICES_ORIGINAL_HASH', (string)$currentHash);
        Option::set($this->MODULE_ID, 'ASPRO_PRICES_PATCHED_HASH', (string)hash_file('sha256', $targetPath));
    }

    protected function frontcalcInstallRestoreAsproPricesTemplate(): void
    {
        $targetPath = (string)Option::get($this->MODULE_ID, 'ASPRO_PRICES_PATH', '');
        $backupPath = (string)Option::get($this->MODULE_ID, 'ASPRO_PRICES_BACKUP_PATH', '');
        $patchedHash = (string)Option::get($this->MODULE_ID, 'ASPRO_PRICES_PATCHED_HASH', '');

        if ($targetPath === '') {
            $targetPath = $this->frontcalcInstallGetAsproPricesPath();
        }
        if ($backupPath === '' || !is_file($backupPath)) {
            return;
        }

        if (is_file($targetPath) && $patchedHash !== '') {
            $currentHash = (string)hash_file('sha256', $targetPath);
            if ($currentHash !== $patchedHash) {
                $this->frontcalcInstallLogWarning(
                    'Файл Aspro prices.php изменён после установки FrontCalc, автоматическое восстановление пропущено: ' . $targetPath . '. Резервная копия: ' . $backupPath
                );
                return;
            }
        }

        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $this->frontcalcInstallLogWarning('Не удалось создать каталог для восстановления prices.php: ' . $targetDir);
            return;
        }

        if (!@copy($backupPath, $targetPath)) {
            $this->frontcalcInstallLogWarning('Не удалось восстановить Aspro prices.php из резервной копии: ' . $backupPath);
        }
    }

    protected function frontcalcInstallGetAsproPricesPath(): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/aspro.premier/lib/product/prices.php';
    }

    protected function frontcalcInstallLogWarning(string $message): void
    {
        if (function_exists('AddMessage2Log')) {
            AddMessage2Log($message, $this->MODULE_ID);
        }
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
        EventManager::getInstance()->registerEventHandlerCompatible(
            'main',
            'OnEpilog',
            $this->MODULE_ID,
            '\\Prospektweb\\Frontcalc\\Service\\FrontendAssets',
            'onEpilog'
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
        EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnEpilog',
            $this->MODULE_ID,
            '\\Prospektweb\\Frontcalc\\Service\\FrontendAssets',
            'onEpilog'
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

        $modulePricesSource = dirname(__DIR__) . '/prices_with_edit.php';
        $modulePricesTarget = $moduleRootPath . '/prices_with_edit.php';
        if (!is_file($modulePricesSource)) {
            throw new \RuntimeException('Не найден исходный файл prices_with_edit.php');
        }
        if (!@copy($modulePricesSource, $modulePricesTarget)) {
            throw new \RuntimeException('Не удалось скопировать prices_with_edit.php в /local/modules');
        }

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

        $moduleConfigSource = dirname(__DIR__) . '/lib/Service/ModuleConfig.php';
        $moduleConfigTarget = $moduleRootPath . '/lib/Service/ModuleConfig.php';
        if (!is_file($moduleConfigSource)) {
            throw new \RuntimeException('Не найден исходный файл lib/Service/ModuleConfig.php');
        }
        if (!@copy($moduleConfigSource, $moduleConfigTarget)) {
            throw new \RuntimeException('Не удалось скопировать lib/Service/ModuleConfig.php в /local/modules');
        }

        $moduleFrontendAssetsSource = dirname(__DIR__) . '/lib/Service/FrontendAssets.php';
        $moduleFrontendAssetsTarget = $moduleRootPath . '/lib/Service/FrontendAssets.php';
        if (!is_file($moduleFrontendAssetsSource)) {
            throw new \RuntimeException('Не найден исходный файл lib/Service/FrontendAssets.php');
        }
        if (!@copy($moduleFrontendAssetsSource, $moduleFrontendAssetsTarget)) {
            throw new \RuntimeException('Не удалось скопировать lib/Service/FrontendAssets.php в /local/modules');
        }

        $moduleHideJsSource = dirname(__DIR__) . '/assets/js/hide-technical-values.js';
        $moduleHideJsTarget = $moduleRootPath . '/assets/js/hide-technical-values.js';
        if (!is_file($moduleHideJsSource)) {
            throw new \RuntimeException('Не найден исходный файл assets/js/hide-technical-values.js');
        }
        if (!@copy($moduleHideJsSource, $moduleHideJsTarget)) {
            throw new \RuntimeException('Не удалось скопировать hide-technical-values.js в /local/modules');
        }

        $moduleAuthJsSource = dirname(__DIR__) . '/assets/js/frontcalc-auth.js';
        $moduleAuthJsTarget = $moduleRootPath . '/assets/js/frontcalc-auth.js';
        if (!is_file($moduleAuthJsSource)) {
            throw new \RuntimeException('Не найден исходный файл assets/js/frontcalc-auth.js');
        }
        if (!@copy($moduleAuthJsSource, $moduleAuthJsTarget)) {
            throw new \RuntimeException('Не удалось скопировать frontcalc-auth.js в /local/modules');
        }

        $moduleHideCssSource = dirname(__DIR__) . '/assets/css/hide-technical-values.css';
        $moduleHideCssTarget = $moduleRootPath . '/assets/css/hide-technical-values.css';
        $moduleHideCssDir = dirname($moduleHideCssTarget);
        if (!is_dir($moduleHideCssDir) && !@mkdir($moduleHideCssDir, 0775, true) && !is_dir($moduleHideCssDir)) {
            throw new \RuntimeException('Не удалось создать каталог /local/modules/.../assets/css');
        }
        if (!is_file($moduleHideCssSource)) {
            throw new \RuntimeException('Не найден исходный файл assets/css/hide-technical-values.css');
        }
        if (!@copy($moduleHideCssSource, $moduleHideCssTarget)) {
            throw new \RuntimeException('Не удалось скопировать hide-technical-values.css в /local/modules');
        }

        $modulePricesCssSource = dirname(__DIR__) . '/assets/css/prices-popup-ext.css';
        $modulePricesCssTarget = $moduleRootPath . '/assets/css/prices-popup-ext.css';
        if (!is_file($modulePricesCssSource)) {
            throw new \RuntimeException('Не найден исходный файл assets/css/prices-popup-ext.css');
        }
        if (!@copy($modulePricesCssSource, $modulePricesCssTarget)) {
            throw new \RuntimeException('Не удалось скопировать prices-popup-ext.css в /local/modules');
        }

        $modulePricesSource = dirname(__DIR__) . '/prices_with_edit.php';
        $modulePricesTarget = $moduleRootPath . '/prices_with_edit.php';
        if (!is_file($modulePricesSource)) {
            throw new \RuntimeException('Не найден исходный файл prices_with_edit.php');
        }
        if (!@copy($modulePricesSource, $modulePricesTarget)) {
            throw new \RuntimeException('Не удалось скопировать prices_with_edit.php в /local/modules');
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
        $modulePricesTarget = $moduleRootPath . '/prices_with_edit.php';
        if (is_file($modulePricesTarget)) {
            @unlink($modulePricesTarget);
        }

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
        $moduleConfigTarget = $moduleRootPath . '/lib/Service/ModuleConfig.php';
        if (is_file($moduleConfigTarget)) {
            @unlink($moduleConfigTarget);
        }
        $moduleFrontendAssetsTarget = $moduleRootPath . '/lib/Service/FrontendAssets.php';
        if (is_file($moduleFrontendAssetsTarget)) {
            @unlink($moduleFrontendAssetsTarget);
        }
        $moduleHideJsTarget = $moduleRootPath . '/assets/js/hide-technical-values.js';
        if (is_file($moduleHideJsTarget)) {
            @unlink($moduleHideJsTarget);
        }
        $moduleAuthJsTarget = $moduleRootPath . '/assets/js/frontcalc-auth.js';
        if (is_file($moduleAuthJsTarget)) {
            @unlink($moduleAuthJsTarget);
        }
        $moduleHideCssTarget = $moduleRootPath . '/assets/css/hide-technical-values.css';
        if (is_file($moduleHideCssTarget)) {
            @unlink($moduleHideCssTarget);
        }
        $modulePricesCssTarget = $moduleRootPath . '/assets/css/prices-popup-ext.css';
        if (is_file($modulePricesCssTarget)) {
            @unlink($modulePricesCssTarget);
        }
        $modulePricesTarget = $moduleRootPath . '/prices_with_edit.php';
        if (is_file($modulePricesTarget)) {
            @unlink($modulePricesTarget);
        }

        @rmdir($moduleRootPath . '/admin');
        @rmdir($moduleRootPath . '/lib/Admin');
        @rmdir($moduleRootPath . '/lib/Service');
        @rmdir($moduleRootPath . '/assets/css');
        @rmdir($moduleRootPath . '/assets/js');
        @rmdir($moduleRootPath . '/assets');
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

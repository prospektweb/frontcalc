<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

global $APPLICATION, $USER;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Access denied');
}

if (!Loader::includeModule('iblock')) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    echo '<div class="adm-info-message-wrap"><div class="adm-info-message">Модуль iblock не подключен.</div></div>';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$moduleId = 'prospektweb.frontcalc';
$elementId = (int)($_REQUEST['ID'] ?? 0);
$iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? 0);
$propertyCode = (string)Option::get($moduleId, 'CALC_PROPERTY_CODE', 'FRONTCALC_CONFIG');

$productsIblockId = (int)Option::get($moduleId, 'PRODUCTS_IBLOCK_ID', '0');
$offersIblockId = (int)Option::get($moduleId, 'OFFERS_IBLOCK_ID', '0');
$hiddenOfferValueIds = (string)Option::get($moduleId, 'HIDDEN_OFFER_VALUE_IDS', '');

$schema = '';
$propertyRes = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $propertyCode]);
$property = $propertyRes->Fetch();
$propertyId = (int)($property['ID'] ?? 0);

if ($elementId > 0 && $propertyId > 0) {
    $propValueRes = CIBlockElement::GetProperty($iblockId, $elementId, [], ['ID' => $propertyId]);
    if ($propValue = $propValueRes->Fetch()) {
        $schema = (string)($propValue['VALUE']['TEXT'] ?? '');
    }
}

$defaultSchema = Option::get($moduleId, 'CALC_EDITOR_SCHEMA', '');
if ($schema === '' && $defaultSchema !== '') {
    $schema = $defaultSchema;
}

$allProperties = [];
$propertyMap = [];
if ($offersIblockId > 0) {
    $res = CIBlockProperty::GetList(
        ['SORT' => 'ASC', 'NAME' => 'ASC'],
        ['IBLOCK_ID' => $offersIblockId, 'ACTIVE' => 'Y', 'PROPERTY_TYPE' => 'L']
    );

    while ($row = $res->Fetch()) {
        if (strpos((string)$row['CODE'], 'CALC_PROP_') !== 0) {
            continue;
        }

        $enumValues = [];
        $enumRes = CIBlockPropertyEnum::GetList(['SORT' => 'ASC'], ['PROPERTY_ID' => $row['ID']]);
        while ($enum = $enumRes->Fetch()) {
            if ((string)$enum['XML_ID'] === '') {
                continue;
            }
            $enumValues[] = [
                'ID' => (int)$enum['ID'],
                'XML_ID' => (string)$enum['XML_ID'],
                'VALUE' => (string)$enum['VALUE'],
            ];
        }

        $item = [
            'CODE' => (string)$row['CODE'],
            'NAME' => (string)$row['NAME'],
            'ENUMS' => $enumValues,
        ];
        $allProperties[] = $item;
        $propertyMap[$item['CODE']] = $item;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid() && $elementId > 0 && $iblockId > 0 && $propertyCode !== '') {
    $schema = trim((string)($_POST['CALC_EDITOR_SCHEMA'] ?? ''));
    $requiredVolumeCode = 'CALC_PROP_VOLUME';
    $postedSchema = json_decode($schema, true);
    if (is_array($postedSchema) && isset($propertyMap[$requiredVolumeCode])) {
        $postedSchema['fields'] = (isset($postedSchema['fields']) && is_array($postedSchema['fields'])) ? $postedSchema['fields'] : [];
        $hasRequiredVolume = false;
        foreach ($postedSchema['fields'] as $field) {
            if ((string)($field['property_code'] ?? '') === $requiredVolumeCode) {
                $hasRequiredVolume = true;
                break;
            }
        }
        if (!$hasRequiredVolume) {
            array_unshift($postedSchema['fields'], [
                'property_code' => $requiredVolumeCode,
                'inputs' => [[
                    'code' => strtolower(str_replace('CALC_PROP_', '', $requiredVolumeCode)),
                    'min' => '',
                    'max' => '',
                    'step' => '',
                    'unit' => '',
                ]],
                'show_presets' => true,
                'show_unit' => true,
                'concat_unit' => false,
                'is_group' => false,
                'group_code' => '',
                'group_delimiter' => 'x',
                'hidden_preset_xml_ids' => [],
                'technical_value_ids' => [],
                'price_driver_type' => 'quantity',
                'calc_options' => [],
            ]);
        }
        $schema = json_encode($postedSchema, JSON_UNESCAPED_UNICODE);
    }
    Option::set($moduleId, 'HIDDEN_OFFER_VALUE_IDS', trim((string)($_POST['HIDDEN_OFFER_VALUE_IDS'] ?? '')));

    CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, [
        $propertyCode => [
            'VALUE' => [
                'TYPE' => 'HTML',
                'TEXT' => $schema,
            ],
        ],
    ]);

    LocalRedirect($APPLICATION->GetCurPageParam('saved=Y', ['saved']));
}

$initialFields = [];
$decoded = json_decode($schema, true);
if (is_array($decoded) && isset($decoded['fields']) && is_array($decoded['fields'])) {
    foreach ($decoded['fields'] as $field) {
        $code = (string)($field['property_code'] ?? '');
        if ($code === '' || !isset($propertyMap[$code])) {
            continue;
        }
        $initialFields[] = $field;
    }
}

$requiredVolumeCode = 'CALC_PROP_VOLUME';
$hasRequiredVolume = false;
foreach ($initialFields as $field) {
    if ((string)($field['property_code'] ?? '') === $requiredVolumeCode) {
        $hasRequiredVolume = true;
        break;
    }
}

if (!$hasRequiredVolume && isset($propertyMap[$requiredVolumeCode])) {
    array_unshift($initialFields, [
        'property_code' => $requiredVolumeCode,
        'inputs' => [[
            'code' => strtolower(str_replace('CALC_PROP_', '', $requiredVolumeCode)),
            'min' => '',
            'max' => '',
            'step' => '',
            'unit' => '',
        ]],
        'show_presets' => true,
        'show_unit' => true,
        'concat_unit' => false,
        'is_group' => false,
        'group_code' => '',
        'group_delimiter' => 'x',
        'hidden_preset_xml_ids' => [],
        'technical_value_ids' => [],
        'price_driver_type' => 'quantity',
        'calc_options' => [],
    ]);
}


$sampleOfferName = '';
if ($elementId > 0 && $productsIblockId > 0 && $offersIblockId > 0 && Loader::includeModule('catalog')) {
    $offersMap = CCatalogSKU::getOffersList(
        [$elementId],
        $productsIblockId,
        ['ACTIVE' => 'Y'],
        ['ID', 'NAME', 'SORT'],
        []
    );
    if (!empty($offersMap[$elementId]) && is_array($offersMap[$elementId])) {
        $sampleOffer = null;
        foreach ($offersMap[$elementId] as $offerRow) {
            if ($sampleOffer === null || (int)($offerRow['SORT'] ?? 500) < (int)($sampleOffer['SORT'] ?? 500)) {
                $sampleOffer = $offerRow;
            }
        }
        if ($sampleOffer !== null) {
            $sampleOfferName = (string)($sampleOffer['NAME'] ?? '');
        }
    }
}

$selectedCodes = [];
foreach ($initialFields as $field) {
    $selectedCodes[] = (string)($field['property_code'] ?? '');
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<style>
.fc-wrap{max-width:980px;width:100%;margin:0 auto;overflow-x:hidden;}
.fc-soft-wrap {background: linear-gradient(180deg,#f8fbff 0%,#f3f7ff 100%);border:1px solid #dce7ff;border-radius:16px;padding:16px;box-shadow:0 10px 24px rgba(34,71,156,.08);margin-bottom:12px;}
.fc-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.fc-card {border-radius:14px;border:1px solid #d9e3f8;background:#fff;box-shadow:0 6px 16px rgba(36,69,146,.08);transition:transform .18s ease, box-shadow .18s ease;overflow:hidden;margin-bottom:10px;}
.fc-card:hover {transform:translateY(-2px);box-shadow:0 12px 24px rgba(30,64,145,.16);} 
.fc-card-head {width:100%;border:0;background:linear-gradient(180deg,#fff 0%,#f2f6ff 100%);text-align:left;padding:10px 12px;display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:14px;font-weight:600;cursor:pointer;}
.fc-card-title{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;max-width:100%;}
.fc-head-actions{display:flex;align-items:center;gap:8px;}
.fc-btn-inline{height:30px;padding:0 10px;border:1px solid #d3ddee;background:#f6f9ff;border-radius:8px;cursor:pointer;font-size:13px;color:#455a84;}
.fc-card-body{padding:12px;border-top:1px solid #e8efff;display:none;} .fc-card.open .fc-card-body{display:block;}
.fc-input,.fc-select{width:100%;height:38px;border:1px solid #cfd9f1;border-radius:10px;padding:0 10px;background:#fff;box-sizing:border-box;min-width:0;}
.fc-input:focus,.fc-select:focus{border-color:#2f6cff;box-shadow:0 0 0 3px rgba(47,108,255,.18);outline:none;}
.fc-row{display:grid;grid-template-columns:repeat(2,minmax(150px,1fr));gap:8px;margin-bottom:8px;}
.fc-pills{display:grid;grid-template-columns:repeat(2,minmax(170px,1fr));gap:8px;margin:10px 0;}
.fc-pill{display:flex;align-items:center;gap:8px;border:1px solid #d7e2fb;background:#f8fbff;border-radius:10px;min-height:38px;padding:0 10px;font-size:13px;}
.fc-subtitle{margin:12px 0 8px;font-size:13px;color:#4d5d7d;font-weight:600;}
.fc-actions{display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;}
.fc-input-block{border:1px dashed #d7e2fb;border-radius:10px;padding:8px;margin-bottom:8px;background:#fbfdff;}
.fc-input-row-actions{display:flex;justify-content:flex-end;margin-top:6px;}
.fc-btn-remove-input{height:30px;padding:0 10px;border:1px solid #f2c1c1;background:#fff5f5;color:#a93434;border-radius:8px;cursor:pointer;}
.fc-driver-panel{border:1px solid #d7e2fb;border-radius:10px;background:#fbfdff;padding:10px;margin:10px 0;}
.fc-driver-options{display:none;margin-top:8px;}
.fc-driver-options.is-active{display:block;}
.fc-help{font-size:12px;color:#687895;margin-top:4px;}
.fc-btn-inline[disabled]{opacity:.45;cursor:not-allowed;}
.fc-title-builder{margin:14px 0;border:1px solid #d9e3f8;border-radius:14px;background:#fff;padding:14px;}
.fc-sample-title{padding:12px;border:1px dashed #b8c8ee;border-radius:10px;background:#f8fbff;line-height:1.5;user-select:text;}
.fc-template-tree{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;}
.fc-template-node{border:1px solid #d7e2fb;border-radius:999px;background:#fff;padding:7px 10px;cursor:pointer;}
.fc-template-node.is-mapped{background:#eef6ff;border-color:#7aa8ff;}
.fc-template-panel{margin-top:10px;padding:10px;border-radius:10px;background:#f7f9ff;}
.fc-match-select{max-width:360px;}
@media (max-width: 900px){.fc-row,.fc-pills{grid-template-columns:1fr;}}
</style>

<div class="fc-wrap">
<?php if (($_GET['saved'] ?? '') === 'Y'): ?>
    <div class="adm-info-message-wrap success"><div class="adm-info-message">Конфигурация сохранена в свойство товара.</div></div>
<?php endif; ?>

<div class="fc-soft-wrap">
    <h2 style="margin-top:0;">Настроить калькулятор</h2>
    <p>Элемент ID: <b><?= (int)$elementId ?></b>, инфоблок: <b><?= (int)$iblockId ?></b>, товары: <b><?= (int)$productsIblockId ?></b>, ТП: <b><?= (int)$offersIblockId ?></b>, свойство: <b><?= htmlspecialcharsbx($propertyCode) ?></b></p>


    <div class="fc-toolbar">
        <select id="fc-add-property" class="fc-select" style="min-width: 280px; max-width: 420px;">
            <option value="">Выберите свойство для добавления…</option>
            <?php foreach ($allProperties as $prop): ?>
                <?php if (in_array($prop['CODE'], $selectedCodes, true)) { continue; } ?>
                <option value="<?= htmlspecialcharsbx($prop['CODE']) ?>"><?= htmlspecialcharsbx($prop['NAME']) ?> (<?= htmlspecialcharsbx($prop['CODE']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="adm-btn" id="fc-add-property-btn">+ Добавить свойство</button>
    </div>
</div>

<form method="post" id="frontcalc-editor-form">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="CALC_EDITOR_SCHEMA" id="fc-schema-json" value="<?= htmlspecialcharsbx($schema) ?>">

    <div id="fc-fields-root">
        <?php foreach ($initialFields as $index => $field): ?>
            <?php
            $code = (string)$field['property_code'];
            $prop = $propertyMap[$code];
            $inputs = (isset($field['inputs']) && is_array($field['inputs']) && !empty($field['inputs'])) ? $field['inputs'] : [[
                'code' => strtolower(str_replace('CALC_PROP_', '', $code)),
                'min' => '',
                'max' => '',
                'step' => '',
                'unit' => '',
            ]];
            $hiddenXml = (isset($field['hidden_preset_xml_ids']) && is_array($field['hidden_preset_xml_ids'])) ? $field['hidden_preset_xml_ids'] : [];
            $technicalValueIds = (isset($field['technical_value_ids']) && is_array($field['technical_value_ids'])) ? $field['technical_value_ids'] : [];
            $priceDriverType = (string)($field['price_driver_type'] ?? ($code === $requiredVolumeCode ? 'quantity' : 'none'));
            $calcOptions = (isset($field['calc_options']) && is_array($field['calc_options'])) ? $field['calc_options'] : [];
            ?>
            <div class="fc-card<?= $index === 0 ? ' open' : '' ?>" data-prop-code="<?= htmlspecialcharsbx($code) ?>">
                <button type="button" class="fc-card-head js-fc-toggle">
                    <span class="fc-card-title"><?= htmlspecialcharsbx($prop['NAME']) ?> <small style="opacity:.65; font-weight:400;">(<?= htmlspecialcharsbx($code) ?>)</small></span>
                    <span class="fc-head-actions">
                        <button type="button" class="fc-btn-inline js-remove-prop"<?= $code === $requiredVolumeCode ? ' disabled title="CALC_PROP_VOLUME обязательно для калькулятора"' : '' ?>>Удалить</button>
                        <span>▾</span>
                    </span>
                </button>
                <div class="fc-card-body">
                    <div class="fc-subtitle">Инпуты поля</div>
                    <div class="js-fc-inputs">
                        <?php foreach ($inputs as $input): ?>
                            <div class="fc-input-block js-fc-input-row">
                                <div class="fc-row">
                                    <input class="fc-input js-inp-code" placeholder="Кодовое название" value="<?= htmlspecialcharsbx((string)($input['code'] ?? '')) ?>">
                                    <input class="fc-input js-inp-min" placeholder="Минимум" value="<?= htmlspecialcharsbx((string)($input['min'] ?? '')) ?>">
                                    <input class="fc-input js-inp-max" placeholder="Максимум" value="<?= htmlspecialcharsbx((string)($input['max'] ?? '')) ?>">
                                    <input class="fc-input js-inp-step" placeholder="Шаг" value="<?= htmlspecialcharsbx((string)($input['step'] ?? '')) ?>">
                                    <input class="fc-input js-inp-unit" placeholder="Ед. изм." value="<?= htmlspecialcharsbx((string)($input['unit'] ?? '')) ?>">
                                </div>
                                <div class="fc-pills">
                                    <label class="fc-pill"><input type="checkbox" class="js-inp-show-unit"<?= array_key_exists('show_unit', $input) ? (!empty($input['show_unit']) ? ' checked' : '') : (!isset($field['show_unit']) || $field['show_unit'] ? ' checked' : '') ?>> Показывать ед. изм.</label>
                                    <label class="fc-pill"><input type="checkbox" class="js-inp-concat-unit"<?= array_key_exists('concat_unit', $input) ? (!empty($input['concat_unit']) ? ' checked' : '') : (!empty($field['concat_unit']) ? ' checked' : '') ?>> Склеивать значение с ед. изм.</label>
                                </div>
                                <div class="fc-input-row-actions">
                                    <button type="button" class="fc-btn-remove-input js-remove-input">Удалить инпут</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="fc-actions">
                        <button type="button" class="adm-btn js-add-input">+ Добавить инпут</button>
                    </div>

                    <div class="fc-pills">
                        <label class="fc-pill"><input type="checkbox" class="js-show-presets"<?= !isset($field['show_presets']) || $field['show_presets'] ? ' checked' : '' ?>> Показывать пресеты</label>
                    </div>

                    <div class="fc-subtitle">Расчёт произвольных значений</div>
                    <div class="fc-driver-panel">
                        <select class="fc-select js-price-driver-type">
                            <option value="none"<?= $priceDriverType === 'none' ? ' selected' : '' ?>>Не влияет на цену</option>
                            <option value="quantity"<?= $priceDriverType === 'quantity' ? ' selected' : '' ?>>Тираж / количество</option>
                            <option value="size_area"<?= $priceDriverType === 'size_area' ? ' selected' : '' ?>>Размер по площади</option>
                            <option value="size_covering"<?= $priceDriverType === 'size_covering' ? ' selected' : '' ?>>Размер по ближайшему большему ТП</option>
                            <option value="pages"<?= $priceDriverType === 'pages' ? ' selected' : '' ?>>Полосы / страницы / листы</option>
                            <option value="production_sheet_delta"<?= $priceDriverType === 'production_sheet_delta' ? ' selected' : '' ?>>Через производственный лист и дельту обработки</option>
                        </select>
                        <div class="fc-help">По умолчанию расчёт выполняется только внутри диапазона опорных ТП.</div>
                        <div class="fc-driver-options js-driver-options">
                            <div class="fc-pills">
                                <label class="fc-pill js-smart-volume-step-wrap"><input type="checkbox" class="js-driver-smart-volume-step"<?= !empty($calcOptions['smart_volume_step']) ? ' checked' : '' ?> title="Работает только при использовании расчёта произвольных значений через производственный лист и дельту обработки"> Использовать «умное» изменение шага, мин. и макс. значения</label>
                            </div>
                            <div class="fc-row">
                                <input class="fc-input js-driver-sensitivity" placeholder="Чувствительность, по умолчанию 1" value="<?= htmlspecialcharsbx((string)($calcOptions['sensitivity'] ?? '')) ?>">
                                <input class="fc-input js-driver-trim" placeholder="Поля, мм" value="<?= htmlspecialcharsbx((string)($calcOptions['trim_margin_mm'] ?? '2')) ?>">
                                <input class="fc-input js-driver-gap" placeholder="Зазор, мм" value="<?= htmlspecialcharsbx((string)($calcOptions['gap_mm'] ?? '0')) ?>">
                            </div>
                            <div class="fc-pills">
                                <label class="fc-pill"><input type="checkbox" class="js-driver-allow-extrapolation"<?= !empty($calcOptions['allow_extrapolation']) ? ' checked' : '' ?>> Разрешить расчёт вне рамок</label>
                                <label class="fc-pill"><input type="checkbox" class="js-driver-allow-rotate"<?= array_key_exists('allow_rotate', $calcOptions) ? (!empty($calcOptions['allow_rotate']) ? ' checked' : '') : ' checked' ?>> Учитывать поворот размера</label>
                            </div>
                        </div>
                    </div>

                    <div class="fc-subtitle">Скрыть варианты (XML_ID)</div>
                    <select class="fc-select js-hidden-presets" multiple size="5" style="height:auto; min-height:120px;">
                        <?php foreach ($prop['ENUMS'] as $enum): ?>
                            <option value="<?= htmlspecialcharsbx($enum['XML_ID']) ?>"<?= in_array($enum['XML_ID'], $hiddenXml, true) ? ' selected' : '' ?>><?= htmlspecialcharsbx($enum['VALUE']) ?> [<?= htmlspecialcharsbx($enum['XML_ID']) ?>]</option>
                        <?php endforeach; ?>
                    </select>

                    <div class="fc-subtitle">Технические варианты (скрыть на фронте)</div>
                    <select class="fc-select js-technical-values" multiple size="5" style="height:auto; min-height:120px;">
                        <?php foreach ($prop['ENUMS'] as $enum): ?>
                            <option value="<?= (int)$enum['ID'] ?>"<?= in_array((int)$enum['ID'], array_map('intval', $technicalValueIds), true) ? ' selected' : '' ?>><?= htmlspecialcharsbx($enum['VALUE']) ?> [ID: <?= (int)$enum['ID'] ?>]</option>
                        <?php endforeach; ?>
                    </select>

                    <div class="fc-subtitle">Групповые настройки (авто при >1 инпута)</div>
                    <div class="js-group-settings" style="display:<?= count($inputs) > 1 ? 'block' : 'none' ?>;">
                        <div class="fc-row">
                            <input class="fc-input js-group-code" placeholder="Код группы" value="<?= htmlspecialcharsbx((string)($field['group_code'] ?? '')) ?>">
                            <input class="fc-input js-group-delimiter" placeholder="Разделитель значений" value="<?= htmlspecialcharsbx((string)($field['group_delimiter'] ?? 'x')) ?>">
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>


    <div class="fc-title-builder">
        <h3 style="margin-top:0;">Шаблон названия ТП</h3>
        <p>Опорное ТП с наименьшей сортировкой:</p>
        <div class="fc-sample-title" id="fc-sample-title"><?= htmlspecialcharsbx($sampleOfferName !== '' ? $sampleOfferName : 'Название опорного ТП не найдено') ?></div>
        <div class="fc-row" style="margin-top:10px;">
            <input class="fc-input" id="fc-title-delimiter" placeholder="Выделите подстроку или введите разделитель вручную">
            <button type="button" class="adm-btn" id="fc-title-split">Разбить выбранный элемент</button>
        </div>
        <div class="fc-subtitle">Предпросмотр разбивки</div>
        <div class="fc-template-tree" id="fc-title-tree"></div>
        <div class="fc-template-panel" id="fc-title-panel">Кликните элемент разбивки, чтобы уточнить или сопоставить его с инпутом.</div>
    </div>

    <br>
    <input type="submit" value="Сохранить" class="adm-btn-save">
</form>
</div>

<script>
(function() {
    const allProperties = <?= \Bitrix\Main\Web\Json::encode($allProperties) ?>;
    const savedSchema = (() => { try { return JSON.parse(<?= \Bitrix\Main\Web\Json::encode($schema ?: '{}') ?>) || {}; } catch (e) { return {}; } })();
    const sampleOfferName = <?= \Bitrix\Main\Web\Json::encode($sampleOfferName) ?>;
    const requiredVolumeCode = 'CALC_PROP_VOLUME';
    const propsByCode = {};
    allProperties.forEach(p => { propsByCode[p.CODE] = p; });

    const root = document.getElementById('fc-fields-root');
    const form = document.getElementById('frontcalc-editor-form');
    const schemaInput = document.getElementById('fc-schema-json');
    const addSelect = document.getElementById('fc-add-property');
    const addBtn = document.getElementById('fc-add-property-btn');
    const titleDelimiter = document.getElementById('fc-title-delimiter');
    const titleSplit = document.getElementById('fc-title-split');
    const titleTree = document.getElementById('fc-title-tree');
    const titlePanel = document.getElementById('fc-title-panel');
    const titleTemplate = savedSchema.title_template && savedSchema.title_template.root ? savedSchema.title_template : {root: {text: sampleOfferName || ''}};
    let selectedTitlePath = [];



    function getNode(path){
        let node = titleTemplate.root;
        (path || []).forEach(index => {
            if (node && Array.isArray(node.children)) node = node.children[index];
        });
        return node;
    }

    function walkNodes(node, path, list){
        list.push({node: node, path: path});
        if (node && Array.isArray(node.children)) {
            node.children.forEach((child, index) => walkNodes(child, path.concat(index), list));
        }
        return list;
    }

    function mappingTargets(){
        const targets = [];
        document.querySelectorAll('.fc-card').forEach(card => {
            const propCode = card.dataset.propCode || '';
            const groupCode = (card.querySelector('.js-group-code')?.value || propCode.replace('CALC_PROP_', '').toLowerCase()).trim();
            const rows = getRows(card);
            if (groupCode) targets.push({value: groupCode, label: groupCode});
            rows.forEach(row => {
                const inputCode = (row.querySelector('.js-inp-code')?.value || '').trim();
                if (!inputCode) return;
                targets.push({value: inputCode, label: inputCode});
                if (groupCode && groupCode !== inputCode) targets.push({value: groupCode + '.' + inputCode, label: inputCode + ' (группа ' + groupCode + ')'});
            });
        });
        const seen = new Set();
        return targets.filter(item => {
            if (seen.has(item.value)) return false;
            seen.add(item.value);
            return true;
        });
    }

    function renderTitleTree(){
        if (!titleTree) return;
        titleTree.innerHTML = '';
        walkNodes(titleTemplate.root, [], []).filter(item => item.path.length || !item.node.children).forEach(item => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'fc-template-node' + (item.node.mapping ? ' is-mapped' : '');
            btn.dataset.path = item.path.join('.');
            btn.textContent = item.node.text || (Array.isArray(item.node.children) ? item.node.children.map(child => child.text).join(item.node.delimiter || '') : '');
            if (item.node.mapping) btn.title = 'Сопоставлено: ' + item.node.mapping.target;
            titleTree.appendChild(btn);
        });
    }

    function splitSelectedTitleNode(){
        const node = getNode(selectedTitlePath);
        const delimiter = titleDelimiter ? titleDelimiter.value : '';
        if (!node || !delimiter) return;
        const text = node.text || (Array.isArray(node.children) ? node.children.map(child => child.text || '').join(node.delimiter || '') : '');
        const parts = String(text).split(delimiter);
        if (parts.length < 2) return;
        node.text = text;
        node.delimiter = delimiter;
        node.children = parts.map(part => ({text: part}));
        delete node.mapping;
        renderTitleTree();
    }

    function renderTitlePanel(path){
        selectedTitlePath = path;
        const node = getNode(path);
        if (!titlePanel || !node) return;
        const text = node.text || '';
        titlePanel.innerHTML = '<b>' + escapeHtml(text) + '</b><div class="fc-actions"><button type="button" class="adm-btn js-title-refine">Уточнить</button><button type="button" class="adm-btn js-title-match">Сопоставить</button></div>';
    }

    function getRows(card){ return Array.from(card.querySelectorAll('.js-fc-input-row')); }
    function syncGroup(card){
        const group = card.querySelector('.js-group-settings');
        const rows = getRows(card);
        if (group) {
            group.style.display = rows.length > 1 ? 'block' : 'none';
        }
        rows.forEach(function(row){
            const removeBtn = row.querySelector('.js-remove-input');
            if (removeBtn) {
                removeBtn.style.display = rows.length > 1 ? 'inline-flex' : 'none';
            }
        });
    }

    function availableCodesInCards(){
        return Array.from(document.querySelectorAll('.fc-card')).map(card => card.dataset.propCode);
    }

    function refreshAddSelect(){
        const selected = availableCodesInCards();
        const current = addSelect.value;
        addSelect.innerHTML = '<option value="">Выберите свойство для добавления…</option>';
        allProperties.forEach(prop => {
            if (selected.indexOf(prop.CODE) !== -1) {
                return;
            }
            const option = document.createElement('option');
            option.value = prop.CODE;
            option.textContent = prop.NAME + ' (' + prop.CODE + ')';
            addSelect.appendChild(option);
        });
        addSelect.value = current;
    }

    function renderEnumOptions(prop){
        const enums = Array.isArray(prop.ENUMS) ? prop.ENUMS : [];
        return enums.map(e => '<option value="' + escapeHtml(e.XML_ID) + '">' + escapeHtml(e.VALUE) + ' [' + escapeHtml(e.XML_ID) + ']</option>').join('');
    }

    function renderTechnicalEnumOptions(prop){
        const enums = Array.isArray(prop.ENUMS) ? prop.ENUMS : [];
        return enums.map(e => '<option value="' + escapeHtml(e.ID) + '">' + escapeHtml(e.VALUE) + ' [ID: ' + escapeHtml(e.ID) + ']</option>').join('');
    }

    function createCard(propCode){
        const prop = propsByCode[propCode];
        if (!prop) {
            return null;
        }

        const html = '\n<div class="fc-card open" data-prop-code="' + escapeHtml(prop.CODE) + '">\n'
            + '  <button type="button" class="fc-card-head js-fc-toggle">\n'
            + '    <span class="fc-card-title">' + escapeHtml(prop.NAME) + ' <small style="opacity:.65; font-weight:400;">(' + escapeHtml(prop.CODE) + ')</small></span>\n'
            + '    <span class="fc-head-actions"><button type="button" class="fc-btn-inline js-remove-prop"' + (prop.CODE === requiredVolumeCode ? ' disabled title="CALC_PROP_VOLUME обязательно для калькулятора"' : '') + '>Удалить</button> <span>▾</span></span>\n'
            + '  </button>\n'
            + '  <div class="fc-card-body">\n'
            + '    <div class="fc-subtitle">Инпуты поля</div>\n'
            + '    <div class="js-fc-inputs">\n'
            + '      <div class="fc-input-block js-fc-input-row">\n'
            + '        <div class="fc-row">\n'
            + '          <input class="fc-input js-inp-code" placeholder="Кодовое название" value="' + escapeHtml(prop.CODE.replace('CALC_PROP_', '').toLowerCase()) + '">\n'
            + '          <input class="fc-input js-inp-min" placeholder="Минимум">\n'
            + '          <input class="fc-input js-inp-max" placeholder="Максимум">\n'
            + '          <input class="fc-input js-inp-step" placeholder="Шаг">\n'
            + '          <input class="fc-input js-inp-unit" placeholder="Ед. изм.">\n'
            + '        </div>\n'
            + '        <div class="fc-pills">\n'
            + '          <label class="fc-pill"><input type="checkbox" class="js-inp-show-unit" checked> Показывать ед. изм.</label>\n'
            + '          <label class="fc-pill"><input type="checkbox" class="js-inp-concat-unit"> Склеивать значение с ед. изм.</label>\n'
            + '        </div>\n'
            + '        <div class="fc-input-row-actions"><button type="button" class="fc-btn-remove-input js-remove-input">Удалить инпут</button></div>\n'
            + '      </div>\n'
            + '    </div>\n'
            + '    <div class="fc-actions"><button type="button" class="adm-btn js-add-input">+ Добавить инпут</button></div>\n'
            + '    <div class="fc-pills">\n'
            + '      <label class="fc-pill"><input type="checkbox" class="js-show-presets" checked> Показывать пресеты</label>\n'
            + '    </div>\n'
            + '    <div class="fc-subtitle">Расчёт произвольных значений</div>\n'
            + '    <div class="fc-driver-panel">\n'
            + '      <select class="fc-select js-price-driver-type">\n'
            + '        <option value="none">Не влияет на цену</option>\n'
            + '        <option value="quantity"' + (prop.CODE === requiredVolumeCode ? ' selected' : '') + '>Тираж / количество</option>\n'
            + '        <option value="size_area">Размер по площади</option>\n'
            + '        <option value="size_covering">Размер по ближайшему большему ТП</option>\n'
            + '        <option value="pages">Полосы / страницы / листы</option>\n'
            + '        <option value="production_sheet_delta">Через производственный лист и дельту обработки</option>\n'
            + '      </select>\n'
            + '      <div class="fc-help">По умолчанию расчёт выполняется только внутри диапазона опорных ТП.</div>\n'
            + '      <div class="fc-driver-options js-driver-options">\n'
            + '        <div class="fc-pills">\n'
            + '          <label class="fc-pill js-smart-volume-step-wrap"><input type="checkbox" class="js-driver-smart-volume-step" title="Работает только при использовании расчёта произвольных значений через производственный лист и дельту обработки"> Использовать «умное» изменение шага, мин. и макс. значения</label>\n'
            + '        </div>\n'
            + '        <div class="fc-row">\n'
            + '          <input class="fc-input js-driver-sensitivity" placeholder="Чувствительность, по умолчанию 1">\n'
            + '          <input class="fc-input js-driver-trim" placeholder="Поля, мм" value="2">\n'
            + '          <input class="fc-input js-driver-gap" placeholder="Зазор, мм" value="0">\n'
            + '        </div>\n'
            + '        <div class="fc-pills">\n'
            + '          <label class="fc-pill"><input type="checkbox" class="js-driver-allow-extrapolation"> Разрешить расчёт вне рамок</label>\n'
            + '          <label class="fc-pill"><input type="checkbox" class="js-driver-allow-rotate" checked> Учитывать поворот размера</label>\n'
            + '        </div>\n'
            + '      </div>\n'
            + '    </div>\n'
            + '    <div class="fc-subtitle">Скрыть варианты (XML_ID)</div>\n'
            + '    <select class="fc-select js-hidden-presets" multiple size="5" style="height:auto; min-height:120px;">' + renderEnumOptions(prop) + '</select>\n'
            + '    <div class="fc-subtitle">Технические варианты (скрыть на фронте)</div>\n'
            + '    <select class="fc-select js-technical-values" multiple size="5" style="height:auto; min-height:120px;">' + renderTechnicalEnumOptions(prop) + '</select>\n'
            + '    <div class="fc-subtitle">Групповые настройки (авто при >1 инпута)</div>\n'
            + '    <div class="js-group-settings" style="display:none;">\n'
            + '      <div class="fc-row">\n'
            + '        <input class="fc-input js-group-code" placeholder="Код группы">\n'
            + '        <input class="fc-input js-group-delimiter" placeholder="Разделитель значений" value="x">\n'
            + '      </div>\n'
            + '    </div>\n'
            + '  </div>\n'
            + '</div>';

        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        return wrap.firstChild;
    }

    function escapeHtml(str){
        return String(str || '').replace(/[&<>'"]/g, function(ch){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','\'' :'&#039;','"':'&quot;'}[ch];
        });
    }

    function syncDriverOptions(card) {
        const type = card.querySelector('.js-price-driver-type')?.value || 'none';
        const panel = card.querySelector('.js-driver-options');
        if (!panel) return;
        panel.classList.toggle('is-active', type !== 'none');

        const smartWrap = card.querySelector('.js-smart-volume-step-wrap');
        if (smartWrap) smartWrap.style.display = type === 'quantity' ? '' : 'none';

        ['sensitivity', 'trim', 'gap'].forEach(name => {
            const input = card.querySelector('.js-driver-' + name);
            if (!input) return;
            const hiddenForQuantity = type === 'quantity';
            const hiddenForProduction = type === 'production_sheet_delta' && name === 'sensitivity';
            input.style.display = hiddenForQuantity || hiddenForProduction ? 'none' : '';
        });
        ['allow-extrapolation', 'allow-rotate'].forEach(name => {
            const input = card.querySelector('.js-driver-' + name);
            const label = input ? input.closest('.fc-pill') : null;
            if (label) label.style.display = type === 'quantity' ? 'none' : '';
        });
    }

    document.querySelectorAll('.fc-card').forEach(syncDriverOptions);

    if (root) {
        root.addEventListener('change', function(event){
            const select = event.target.closest('.js-price-driver-type');
            if (select) {
                const card = select.closest('.fc-card');
                if (card) syncDriverOptions(card);
            }
        });

        root.addEventListener('click', function(event){
            const toggle = event.target.closest('.js-fc-toggle');
            if (toggle) {
                if (event.target.closest('.js-remove-prop')) {
                    return;
                }
                const card = toggle.closest('.fc-card');
                if (card) {
                    card.classList.toggle('open');
                }
                return;
            }

            const addInput = event.target.closest('.js-add-input');
            if (addInput) {
                const card = addInput.closest('.fc-card');
                const rows = card.querySelector('.js-fc-inputs');
                const row = card.querySelector('.js-fc-input-row');
                const clone = row.cloneNode(true);
                clone.querySelectorAll('input').forEach(inp => inp.value = '');
                rows.appendChild(clone);
                syncGroup(card);
                return;
            }

            const removeInput = event.target.closest('.js-remove-input');
            if (removeInput) {
                const card = removeInput.closest('.fc-card');
                const row = removeInput.closest('.js-fc-input-row');
                if (card && row) {
                    row.remove();
                    syncGroup(card);
                }
                return;
            }

            const removeProp = event.target.closest('.js-remove-prop');
            if (removeProp) {
                const card = removeProp.closest('.fc-card');
                if (card && card.dataset.propCode !== requiredVolumeCode) {
                    card.remove();
                    refreshAddSelect();
                }
            }
        });
    }

    if (addBtn) {
        addBtn.addEventListener('click', function(){
            const code = addSelect.value;
            if (!code) {
                return;
            }
            const card = createCard(code);
            if (!card) {
                return;
            }
            root.appendChild(card);
            syncDriverOptions(card);
            refreshAddSelect();
        });
    }



    document.addEventListener('selectionchange', function(){
        const selected = String(window.getSelection ? window.getSelection().toString() : '').trim();
        if (selected && titleDelimiter) titleDelimiter.value = selected;
    });
    if (titleSplit) titleSplit.addEventListener('click', splitSelectedTitleNode);
    if (titleTree) titleTree.addEventListener('click', function(event){
        const nodeButton = event.target.closest('.fc-template-node');
        if (!nodeButton) return;
        const path = (nodeButton.dataset.path || '').split('.').filter(Boolean).map(v => parseInt(v, 10));
        renderTitlePanel(path);
    });
    if (titlePanel) titlePanel.addEventListener('click', function(event){
        if (event.target.closest('.js-title-refine')) {
            if (titleDelimiter) titleDelimiter.focus();
            return;
        }
        if (event.target.closest('.js-title-match')) {
            const targets = mappingTargets();
            const select = '<select class="fc-select fc-match-select js-title-target">' + targets.map(t => '<option value="' + escapeHtml(t.value) + '">' + escapeHtml(t.label) + '</option>').join('') + '</select><button type="button" class="adm-btn js-title-save-match">Сохранить сопоставление</button>';
            titlePanel.insertAdjacentHTML('beforeend', '<div class="fc-actions">' + select + '</div>');
            return;
        }
        if (event.target.closest('.js-title-save-match')) {
            const node = getNode(selectedTitlePath);
            const value = titlePanel.querySelector('.js-title-target')?.value || '';
            if (node && value) {
                node.mapping = {target: value};
                renderTitleTree();
            }
        }
    });

    if (form) {
        form.addEventListener('submit', function(){
            const fields = [];
            document.querySelectorAll('.fc-card').forEach(card => {
                const inputs = getRows(card).map(row => ({
                    code: row.querySelector('.js-inp-code').value || '',
                    min: row.querySelector('.js-inp-min').value || '',
                    max: row.querySelector('.js-inp-max').value || '',
                    step: row.querySelector('.js-inp-step').value || '',
                    unit: row.querySelector('.js-inp-unit').value || '',
                    show_unit: row.querySelector('.js-inp-show-unit') ? row.querySelector('.js-inp-show-unit').checked : true,
                    concat_unit: row.querySelector('.js-inp-concat-unit') ? row.querySelector('.js-inp-concat-unit').checked : false
                }));
                const hiddenPresetXmlIds = Array.from(card.querySelector('.js-hidden-presets').selectedOptions).map(opt => opt.value);
                const technicalSelect = card.querySelector('.js-technical-values');
                const technicalValueIds = technicalSelect ? Array.from(technicalSelect.selectedOptions).map(opt => parseInt(opt.value, 10)).filter(Boolean) : [];

                const priceDriverType = card.querySelector('.js-price-driver-type') ? card.querySelector('.js-price-driver-type').value : 'none';
                const calcOptions = {
                    sensitivity: card.querySelector('.js-driver-sensitivity') ? card.querySelector('.js-driver-sensitivity').value : '',
                    trim_margin_mm: card.querySelector('.js-driver-trim') ? card.querySelector('.js-driver-trim').value : '',
                    gap_mm: card.querySelector('.js-driver-gap') ? card.querySelector('.js-driver-gap').value : '',
                    allow_extrapolation: card.querySelector('.js-driver-allow-extrapolation') ? card.querySelector('.js-driver-allow-extrapolation').checked : false,
                    allow_rotate: card.querySelector('.js-driver-allow-rotate') ? card.querySelector('.js-driver-allow-rotate').checked : true,
                    smart_volume_step: card.querySelector('.js-driver-smart-volume-step') ? card.querySelector('.js-driver-smart-volume-step').checked : false
                };

                fields.push({
                    property_code: card.dataset.propCode || '',
                    inputs: inputs,
                    show_presets: card.querySelector('.js-show-presets').checked,
                    show_unit: inputs.some(input => input.show_unit),
                    concat_unit: inputs.some(input => input.concat_unit),
                    is_group: inputs.length > 1,
                    group_code: card.querySelector('.js-group-code').value || '',
                    group_delimiter: card.querySelector('.js-group-delimiter').value || 'x',
                    hidden_preset_xml_ids: hiddenPresetXmlIds,
                    technical_value_ids: technicalValueIds,
                    price_driver_type: priceDriverType,
                    calc_options: calcOptions
                });
            });

            const allTechnicalIds = Array.from(new Set(fields.reduce((acc, field) => acc.concat(field.technical_value_ids || []), []))).join(',');
            let hiddenInput = form.querySelector('input[name="HIDDEN_OFFER_VALUE_IDS"]');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'HIDDEN_OFFER_VALUE_IDS';
                form.appendChild(hiddenInput);
            }
            hiddenInput.value = allTechnicalIds;
            schemaInput.value = JSON.stringify({version: 1, fields: fields, title_template: titleTemplate});
        });
    }

    document.querySelectorAll('.fc-card').forEach(syncGroup);
    refreshAddSelect();
    renderTitleTree();
})();
</script>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>

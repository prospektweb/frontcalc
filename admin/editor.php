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

$calcProperties = [];
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
                'XML_ID' => (string)$enum['XML_ID'],
                'VALUE' => (string)$enum['VALUE'],
            ];
        }

        $calcProperties[] = [
            'CODE' => (string)$row['CODE'],
            'NAME' => (string)$row['NAME'],
            'ENUMS' => $enumValues,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid() && $elementId > 0 && $iblockId > 0 && $propertyCode !== '') {
    $schema = trim((string)($_POST['CALC_EDITOR_SCHEMA'] ?? ''));

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

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<style>
.fc-soft-wrap {background: linear-gradient(180deg,#f8fbff 0%,#f3f7ff 100%);border:1px solid #dce7ff;border-radius:16px;padding:16px;box-shadow:0 10px 24px rgba(34,71,156,.08);margin-bottom:16px;}
.fc-card {border-radius:14px;border:1px solid #d9e3f8;background:#fff;box-shadow:0 6px 16px rgba(36,69,146,.08);transition:transform .18s ease, box-shadow .18s ease;overflow:hidden;margin-bottom:10px;}
.fc-card:hover {transform:translateY(-2px);box-shadow:0 12px 24px rgba(30,64,145,.16);} 
.fc-card-head {width:100%;border:0;background:linear-gradient(180deg,#fff 0%,#f2f6ff 100%);text-align:left;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;font-size:14px;font-weight:600;cursor:pointer;}
.fc-card-body{padding:14px;border-top:1px solid #e8efff;display:none;} .fc-card.open .fc-card-body{display:block;}
.fc-input,.fc-select{width:100%;height:38px;border:1px solid #cfd9f1;border-radius:10px;padding:0 10px;background:#fff;box-sizing:border-box;}
.fc-input:focus,.fc-select:focus{border-color:#2f6cff;box-shadow:0 0 0 3px rgba(47,108,255,.18);outline:none;}
.fc-row{display:grid;grid-template-columns:repeat(5,minmax(120px,1fr));gap:10px;margin-bottom:10px;}
.fc-pills{display:grid;grid-template-columns:repeat(2,minmax(170px,1fr));gap:8px;margin:10px 0;}
.fc-pill{display:flex;align-items:center;gap:8px;border:1px solid #d7e2fb;background:#f8fbff;border-radius:10px;min-height:38px;padding:0 10px;font-size:13px;}
.fc-subtitle{margin:14px 0 8px;font-size:13px;color:#4d5d7d;font-weight:600;}
.fc-actions{display:flex;gap:8px;margin-top:8px;}
@media (max-width: 1100px){.fc-row{grid-template-columns:repeat(2,minmax(120px,1fr));}}
</style>

<?php if (($_GET['saved'] ?? '') === 'Y'): ?>
    <div class="adm-info-message-wrap success"><div class="adm-info-message">Конфигурация сохранена в свойство товара.</div></div>
<?php endif; ?>

<div class="fc-soft-wrap">
    <h2 style="margin-top:0;">Настроить калькулятор</h2>
    <p>Элемент ID: <b><?= (int)$elementId ?></b>, инфоблок: <b><?= (int)$iblockId ?></b>, товары: <b><?= (int)$productsIblockId ?></b>, ТП: <b><?= (int)$offersIblockId ?></b>, свойство: <b><?= htmlspecialcharsbx($propertyCode) ?></b></p>
</div>

<form method="post" id="frontcalc-editor-form">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="CALC_EDITOR_SCHEMA" id="fc-schema-json" value="<?= htmlspecialcharsbx($schema) ?>">

    <?php if (empty($calcProperties)): ?>
        <div class="adm-info-message-wrap"><div class="adm-info-message">Не найдены list-свойства CALC_PROP_* в OFFERS_IBLOCK_ID.</div></div>
    <?php endif; ?>

    <div id="fc-fields-root">
        <?php foreach ($calcProperties as $index => $property): ?>
            <div class="fc-card<?= $index === 0 ? ' open' : '' ?>" data-prop-code="<?= htmlspecialcharsbx($property['CODE']) ?>">
                <button type="button" class="fc-card-head js-fc-toggle">
                    <span><?= htmlspecialcharsbx($property['NAME']) ?> <small style="opacity:.65; font-weight:400;">(<?= htmlspecialcharsbx($property['CODE']) ?>)</small></span>
                    <span>▾</span>
                </button>
                <div class="fc-card-body">
                    <div class="fc-subtitle">Инпуты поля</div>
                    <div class="js-fc-inputs">
                        <div class="fc-row js-fc-input-row">
                            <input class="fc-input js-inp-code" placeholder="Кодовое название" value="<?= htmlspecialcharsbx(strtolower(str_replace('CALC_PROP_', '', $property['CODE']))) ?>">
                            <input class="fc-input js-inp-min" placeholder="Минимум">
                            <input class="fc-input js-inp-max" placeholder="Максимум">
                            <input class="fc-input js-inp-step" placeholder="Шаг">
                            <input class="fc-input js-inp-unit" placeholder="Ед. изм.">
                        </div>
                    </div>

                    <div class="fc-actions">
                        <button type="button" class="adm-btn js-add-input">+ Добавить инпут</button>
                    </div>

                    <div class="fc-pills">
                        <label class="fc-pill"><input type="checkbox" class="js-show-presets" checked> Показывать пресеты</label>
                        <label class="fc-pill"><input type="checkbox" class="js-show-unit" checked> Показывать ед. изм.</label>
                        <label class="fc-pill"><input type="checkbox" class="js-concat-unit"> Склеивать значение с ед. изм.</label>
                    </div>

                    <div class="fc-subtitle">Скрыть варианты (XML_ID)</div>
                    <select class="fc-select js-hidden-presets" multiple size="5" style="height:auto; min-height:130px;">
                        <?php foreach ($property['ENUMS'] as $enum): ?>
                            <option value="<?= htmlspecialcharsbx($enum['XML_ID']) ?>"><?= htmlspecialcharsbx($enum['VALUE']) ?> [<?= htmlspecialcharsbx($enum['XML_ID']) ?>]</option>
                        <?php endforeach; ?>
                    </select>

                    <div class="fc-subtitle">Групповые настройки (авто при >1 инпута)</div>
                    <div class="js-group-settings" style="display:none;">
                        <div class="fc-row" style="grid-template-columns:repeat(2,minmax(140px,1fr));">
                            <input class="fc-input js-group-code" placeholder="Код группы">
                            <input class="fc-input js-group-delimiter" placeholder="Разделитель значений" value="x">
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <br>
    <input type="submit" value="Сохранить" class="adm-btn-save">
</form>

<script>
(function() {
    const root = document.getElementById('fc-fields-root');
    const form = document.getElementById('frontcalc-editor-form');
    const schemaInput = document.getElementById('fc-schema-json');

    function getRows(card) { return Array.from(card.querySelectorAll('.js-fc-input-row')); }

    function syncGroup(card) {
        const groupPanel = card.querySelector('.js-group-settings');
        const isGroup = getRows(card).length > 1;
        if (groupPanel) {
            groupPanel.style.display = isGroup ? 'block' : 'none';
        }
    }

    function setRowValues(row, data) {
        row.querySelector('.js-inp-code').value = data.code || '';
        row.querySelector('.js-inp-min').value = data.min || '';
        row.querySelector('.js-inp-max').value = data.max || '';
        row.querySelector('.js-inp-step').value = data.step || '';
        row.querySelector('.js-inp-unit').value = data.unit || '';
    }

    function applySchema() {
        if (!schemaInput.value) {
            document.querySelectorAll('.fc-card').forEach(syncGroup);
            return;
        }

        let parsed;
        try { parsed = JSON.parse(schemaInput.value); } catch (e) { parsed = null; }
        if (!parsed || !Array.isArray(parsed.fields)) {
            document.querySelectorAll('.fc-card').forEach(syncGroup);
            return;
        }

        const map = {};
        parsed.fields.forEach(field => { if (field && field.property_code) { map[field.property_code] = field; } });

        document.querySelectorAll('.fc-card').forEach(card => {
            const code = card.dataset.propCode;
            const field = map[code];
            if (!field) { syncGroup(card); return; }

            card.querySelector('.js-show-presets').checked = !!field.show_presets;
            card.querySelector('.js-show-unit').checked = !!field.show_unit;
            card.querySelector('.js-concat-unit').checked = !!field.concat_unit;
            card.querySelector('.js-group-code').value = field.group_code || '';
            card.querySelector('.js-group-delimiter').value = field.group_delimiter || 'x';

            const select = card.querySelector('.js-hidden-presets');
            const hidden = Array.isArray(field.hidden_preset_xml_ids) ? field.hidden_preset_xml_ids : [];
            Array.from(select.options).forEach(opt => { opt.selected = hidden.indexOf(opt.value) !== -1; });

            const inputs = Array.isArray(field.inputs) && field.inputs.length ? field.inputs : [];
            if (inputs.length) {
                const container = card.querySelector('.js-fc-inputs');
                const base = card.querySelector('.js-fc-input-row');
                container.innerHTML = '';
                inputs.forEach((input, i) => {
                    const row = i === 0 ? base : base.cloneNode(true);
                    setRowValues(row, input || {});
                    container.appendChild(row);
                });
            }

            syncGroup(card);
        });
    }

    if (root) {
        root.addEventListener('click', function(event) {
            const t = event.target;
            const toggle = t.closest('.js-fc-toggle');
            if (toggle) {
                const card = toggle.closest('.fc-card');
                card.classList.toggle('open');
                return;
            }

            const addBtn = t.closest('.js-add-input');
            if (addBtn) {
                const card = addBtn.closest('.fc-card');
                const container = card.querySelector('.js-fc-inputs');
                const row = card.querySelector('.js-fc-input-row');
                const clone = row.cloneNode(true);
                clone.querySelectorAll('input').forEach(input => input.value = '');
                container.appendChild(clone);
                syncGroup(card);
            }
        });
    }

    if (form) {
        applySchema();

        form.addEventListener('submit', function() {
            const fields = [];

            document.querySelectorAll('.fc-card').forEach(card => {
                const inputs = [];
                getRows(card).forEach(row => {
                    inputs.push({
                        code: row.querySelector('.js-inp-code').value || '',
                        min: row.querySelector('.js-inp-min').value || '',
                        max: row.querySelector('.js-inp-max').value || '',
                        step: row.querySelector('.js-inp-step').value || '',
                        unit: row.querySelector('.js-inp-unit').value || ''
                    });
                });

                const hiddenPresetXmlIds = Array.from(card.querySelector('.js-hidden-presets').selectedOptions).map(opt => opt.value);

                fields.push({
                    property_code: card.dataset.propCode || '',
                    inputs: inputs,
                    show_presets: card.querySelector('.js-show-presets').checked,
                    show_unit: card.querySelector('.js-show-unit').checked,
                    concat_unit: card.querySelector('.js-concat-unit').checked,
                    is_group: inputs.length > 1,
                    group_code: card.querySelector('.js-group-code').value || '',
                    group_delimiter: card.querySelector('.js-group-delimiter').value || 'x',
                    hidden_preset_xml_ids: hiddenPresetXmlIds
                });
            });

            schemaInput.value = JSON.stringify({version: 1, fields: fields});
        });
    }
})();
</script>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>

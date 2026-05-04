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
            ?>
            <div class="fc-card<?= $index === 0 ? ' open' : '' ?>" data-prop-code="<?= htmlspecialcharsbx($code) ?>">
                <button type="button" class="fc-card-head js-fc-toggle">
                    <span class="fc-card-title"><?= htmlspecialcharsbx($prop['NAME']) ?> <small style="opacity:.65; font-weight:400;">(<?= htmlspecialcharsbx($code) ?>)</small></span>
                    <span class="fc-head-actions">
                        <button type="button" class="fc-btn-inline js-remove-prop">Удалить</button>
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
                        <label class="fc-pill"><input type="checkbox" class="js-show-unit"<?= !isset($field['show_unit']) || $field['show_unit'] ? ' checked' : '' ?>> Показывать ед. изм.</label>
                        <label class="fc-pill"><input type="checkbox" class="js-concat-unit"<?= !empty($field['concat_unit']) ? ' checked' : '' ?>> Склеивать значение с ед. изм.</label>
                    </div>

                    <div class="fc-subtitle">Скрыть варианты (XML_ID)</div>
                    <select class="fc-select js-hidden-presets" multiple size="5" style="height:auto; min-height:120px;">
                        <?php foreach ($prop['ENUMS'] as $enum): ?>
                            <option value="<?= htmlspecialcharsbx($enum['XML_ID']) ?>"<?= in_array($enum['XML_ID'], $hiddenXml, true) ? ' selected' : '' ?>><?= htmlspecialcharsbx($enum['VALUE']) ?> [<?= htmlspecialcharsbx($enum['XML_ID']) ?>]</option>
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

    <br>
    <input type="submit" value="Сохранить" class="adm-btn-save">
</form>
</div>

<script>
(function() {
    const allProperties = <?= \Bitrix\Main\Web\Json::encode($allProperties) ?>;
    const propsByCode = {};
    allProperties.forEach(p => { propsByCode[p.CODE] = p; });

    const root = document.getElementById('fc-fields-root');
    const form = document.getElementById('frontcalc-editor-form');
    const schemaInput = document.getElementById('fc-schema-json');
    const addSelect = document.getElementById('fc-add-property');
    const addBtn = document.getElementById('fc-add-property-btn');

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

    function createCard(propCode){
        const prop = propsByCode[propCode];
        if (!prop) {
            return null;
        }

        const html = '\n<div class="fc-card open" data-prop-code="' + escapeHtml(prop.CODE) + '">\n'
            + '  <button type="button" class="fc-card-head js-fc-toggle">\n'
            + '    <span class="fc-card-title">' + escapeHtml(prop.NAME) + ' <small style="opacity:.65; font-weight:400;">(' + escapeHtml(prop.CODE) + ')</small></span>\n'
            + '    <span class="fc-head-actions"><button type="button" class="fc-btn-inline js-remove-prop">Удалить</button> <span>▾</span></span>\n'
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
            + '        <div class="fc-input-row-actions"><button type="button" class="fc-btn-remove-input js-remove-input">Удалить инпут</button></div>\n'
            + '      </div>\n'
            + '    </div>\n'
            + '    <div class="fc-actions"><button type="button" class="adm-btn js-add-input">+ Добавить инпут</button></div>\n'
            + '    <div class="fc-pills">\n'
            + '      <label class="fc-pill"><input type="checkbox" class="js-show-presets" checked> Показывать пресеты</label>\n'
            + '      <label class="fc-pill"><input type="checkbox" class="js-show-unit" checked> Показывать ед. изм.</label>\n'
            + '      <label class="fc-pill"><input type="checkbox" class="js-concat-unit"> Склеивать значение с ед. изм.</label>\n'
            + '    </div>\n'
            + '    <div class="fc-subtitle">Скрыть варианты (XML_ID)</div>\n'
            + '    <select class="fc-select js-hidden-presets" multiple size="5" style="height:auto; min-height:120px;">' + renderEnumOptions(prop) + '</select>\n'
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

    if (root) {
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
                if (card) {
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
            refreshAddSelect();
        });
    }

    if (form) {
        form.addEventListener('submit', function(){
            const fields = [];
            document.querySelectorAll('.fc-card').forEach(card => {
                const inputs = getRows(card).map(row => ({
                    code: row.querySelector('.js-inp-code').value || '',
                    min: row.querySelector('.js-inp-min').value || '',
                    max: row.querySelector('.js-inp-max').value || '',
                    step: row.querySelector('.js-inp-step').value || '',
                    unit: row.querySelector('.js-inp-unit').value || ''
                }));
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

    document.querySelectorAll('.fc-card').forEach(syncGroup);
    refreshAddSelect();
})();
</script>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>

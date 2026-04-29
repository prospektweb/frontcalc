<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

$moduleId = 'prospektweb.frontcalc';
Loader::includeModule($moduleId);

/** @global CMain $APPLICATION */
/** @global CUser $USER */
global $APPLICATION, $USER;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Access denied');
}

$calcProperties = [];
$offersIblockId = (int)Option::get($moduleId, 'OFFERS_IBLOCK_ID', '0');

if ($offersIblockId > 0 && Loader::includeModule('iblock')) {
    $res = CIBlockProperty::GetList(
        ['SORT' => 'ASC', 'NAME' => 'ASC'],
        [
            'IBLOCK_ID' => $offersIblockId,
            'ACTIVE' => 'Y',
            'PROPERTY_TYPE' => 'L',
        ]
    );

    while ($property = $res->Fetch()) {
        if (strpos((string)$property['CODE'], 'CALC_PROP_') !== 0) {
            continue;
        }

        $calcProperties[] = [
            'ID' => (int)$property['ID'],
            'CODE' => (string)$property['CODE'],
            'NAME' => (string)$property['NAME'],
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    Option::set($moduleId, 'PRODUCTS_IBLOCK_ID', trim((string)($_POST['PRODUCTS_IBLOCK_ID'] ?? '0')));
    Option::set($moduleId, 'OFFERS_IBLOCK_ID', trim((string)($_POST['OFFERS_IBLOCK_ID'] ?? '0')));

    $schema = (string)($_POST['CALC_EDITOR_SCHEMA'] ?? '');
    if ($schema !== '') {
        Option::set($moduleId, 'CALC_EDITOR_SCHEMA', $schema);
    }

    LocalRedirect($APPLICATION->GetCurPage() . '?mid=' . urlencode($moduleId) . '&lang=' . LANGUAGE_ID . '&saved=Y');
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$products = Option::get($moduleId, 'PRODUCTS_IBLOCK_ID', '0');
$offers = Option::get($moduleId, 'OFFERS_IBLOCK_ID', '0');
$editorSchema = Option::get($moduleId, 'CALC_EDITOR_SCHEMA', '');
?>
<style>
    .fc-soft-wrap {
        background: linear-gradient(180deg, #f8fbff 0%, #f3f7ff 100%);
        border: 1px solid #dce7ff;
        border-radius: 16px;
        padding: 18px;
        box-shadow: 0 10px 24px rgba(34, 71, 156, 0.08);
        margin-bottom: 16px;
    }
    .fc-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(240px, 1fr));
        gap: 12px;
    }
    .fc-card {
        border-radius: 14px;
        border: 1px solid #d9e3f8;
        background: #fff;
        box-shadow: 0 6px 16px rgba(36, 69, 146, 0.08);
        transition: transform .18s ease, box-shadow .18s ease;
        overflow: hidden;
    }
    .fc-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(30, 64, 145, 0.16);
    }
    .fc-card-head {
        width: 100%;
        border: 0;
        background: linear-gradient(180deg, #ffffff 0%, #f2f6ff 100%);
        text-align: left;
        padding: 12px 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
    }
    .fc-card-body {
        padding: 14px;
        border-top: 1px solid #e8efff;
        display: none;
    }
    .fc-card.open .fc-card-body { display: block; }
    .fc-input, .fc-select {
        width: 100%;
        height: 38px;
        border: 1px solid #cfd9f1;
        border-radius: 10px;
        padding: 0 10px;
        background: #fff;
        box-sizing: border-box;
    }
    .fc-input:focus, .fc-select:focus {
        border-color: #2f6cff;
        box-shadow: 0 0 0 3px rgba(47, 108, 255, 0.18);
        outline: none;
    }
    .fc-row {
        display: grid;
        grid-template-columns: repeat(4, minmax(130px, 1fr));
        gap: 10px;
        margin-bottom: 10px;
    }
    .fc-pills {
        display: grid;
        grid-template-columns: repeat(2, minmax(150px, 1fr));
        gap: 8px;
        margin: 10px 0;
    }
    .fc-pill {
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid #d7e2fb;
        background: #f8fbff;
        border-radius: 10px;
        min-height: 38px;
        padding: 0 10px;
        font-size: 13px;
    }
    .fc-subtitle {
        margin: 14px 0 8px;
        font-size: 13px;
        color: #4d5d7d;
        font-weight: 600;
    }
    .fc-muted {
        color: #6f7d99;
        font-size: 12px;
        margin-top: 6px;
    }
    .fc-actions {
        display: flex;
        gap: 8px;
        margin-top: 8px;
    }
    @media (max-width: 1100px) {
        .fc-grid { grid-template-columns: 1fr; }
        .fc-row { grid-template-columns: repeat(2, minmax(130px, 1fr)); }
    }
</style>

<?php if (($_GET['saved'] ?? '') === 'Y'): ?>
    <div class="adm-info-message-wrap success"><div class="adm-info-message">Сохранено</div></div>
<?php endif; ?>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>" id="frontcalc-options-form">
    <?= bitrix_sessid_post(); ?>

    <div class="fc-soft-wrap">
        <div class="fc-grid">
            <div>
                <div class="fc-subtitle">PRODUCTS_IBLOCK_ID</div>
                <input class="fc-input" type="text" name="PRODUCTS_IBLOCK_ID" value="<?= htmlspecialcharsbx($products) ?>">
            </div>
            <div>
                <div class="fc-subtitle">OFFERS_IBLOCK_ID</div>
                <input class="fc-input" type="text" name="OFFERS_IBLOCK_ID" value="<?= htmlspecialcharsbx($offers) ?>">
            </div>
        </div>
        <p class="fc-muted">Карточный редактор настроек калькулятора (soft admin UI).</p>
    </div>

    <input type="hidden" name="CALC_EDITOR_SCHEMA" id="fc-schema-json" value="<?= htmlspecialcharsbx($editorSchema) ?>">

    <div id="fc-fields-root">
        <?php foreach ($calcProperties as $index => $property): ?>
            <div class="fc-card<?= $index === 0 ? ' open' : '' ?>" data-prop-code="<?= htmlspecialcharsbx($property['CODE']) ?>">
                <button type="button" class="fc-card-head js-fc-toggle">
                    <span><?= htmlspecialcharsbx($property['NAME']) ?> <small style="opacity:.65; font-weight:400;">(<?= htmlspecialcharsbx($property['CODE']) ?>)</small></span>
                    <span>▾</span>
                </button>
                <div class="fc-card-body">
                    <div class="fc-subtitle">Свойство ТП</div>
                    <select class="fc-select js-fc-prop-select">
                        <?php foreach ($calcProperties as $option): ?>
                            <option value="<?= htmlspecialcharsbx($option['CODE']) ?>"<?= $option['CODE'] === $property['CODE'] ? ' selected' : '' ?>>
                                <?= htmlspecialcharsbx($option['NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

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
                        <input type="hidden" class="js-group-enabled" value="N">
                    </div>

                    <div class="js-group-settings" style="display:none;">
                        <div class="fc-row">
                            <input class="fc-input js-group-code" placeholder="Код группы">
                            <input class="fc-input js-group-delimiter" placeholder="Разделитель значений" value="x">
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($calcProperties)): ?>
        <div class="adm-info-message-wrap">
            <div class="adm-info-message">Для OFFERS_IBLOCK_ID=<?= (int)$offersIblockId ?> не найдено list-свойств с кодом CALC_PROP_*</div>
        </div>
    <?php endif; ?>

    <br>
    <input type="submit" value="Сохранить" class="adm-btn-save">
</form>

<script>
(function() {
    const root = document.getElementById('fc-fields-root');
    const form = document.getElementById('frontcalc-options-form');
    const schemaInput = document.getElementById('fc-schema-json');

    if (root) {
        root.addEventListener('click', function(event) {
            const toggle = event.target.closest('.js-fc-toggle');
            if (toggle) {
                const card = toggle.closest('.fc-card');
                if (card) {
                    card.classList.toggle('open');
                }
                return;
            }

            const addBtn = event.target.closest('.js-add-input');
            if (addBtn) {
                const card = addBtn.closest('.fc-card');
                const container = card.querySelector('.js-fc-inputs');
                const row = card.querySelector('.js-fc-input-row');
                if (!container || !row) {
                    return;
                }
                const clone = row.cloneNode(true);
                clone.querySelectorAll('input').forEach(function(input) { input.value = ''; });
                container.appendChild(clone);
                syncGroupState(card);
            }
        });
    }

    if (form && schemaInput) {
        tryApplySavedSchema();

        form.addEventListener('submit', function() {
            const schema = [];
            document.querySelectorAll('.fc-card').forEach(function(card) {
                const propertyCode = card.dataset.propCode || '';
                const propertySelect = card.querySelector('.js-fc-prop-select');
                const selectedCode = propertySelect ? propertySelect.value : propertyCode;
                const rows = [];
                const isGroup = getInputRows(card).length > 1;

                card.querySelectorAll('.js-fc-input-row').forEach(function(row) {
                    rows.push({
                        code: (row.querySelector('.js-inp-code') || {}).value || '',
                        min: (row.querySelector('.js-inp-min') || {}).value || '',
                        max: (row.querySelector('.js-inp-max') || {}).value || '',
                        step: (row.querySelector('.js-inp-step') || {}).value || '',
                        unit: (row.querySelector('.js-inp-unit') || {}).value || ''
                    });
                });

                schema.push({
                    property_code: selectedCode,
                    inputs: rows,
                    show_presets: !!(card.querySelector('.js-show-presets') || {}).checked,
                    show_unit: !!(card.querySelector('.js-show-unit') || {}).checked,
                    concat_unit: !!(card.querySelector('.js-concat-unit') || {}).checked,
                    is_group: isGroup,
                    group_code: ((card.querySelector('.js-group-code') || {}).value || ''),
                    group_delimiter: ((card.querySelector('.js-group-delimiter') || {}).value || '')
                });
            });

            schemaInput.value = JSON.stringify({version: 1, fields: schema});
        });
    }

    function getInputRows(card) {
        return Array.prototype.slice.call(card.querySelectorAll('.js-fc-input-row'));
    }

    function syncGroupState(card) {
        const hiddenGroupFlag = card.querySelector('.js-group-enabled');
        const groupPanel = card.querySelector('.js-group-settings');
        const isGroup = getInputRows(card).length > 1;

        if (hiddenGroupFlag) {
            hiddenGroupFlag.value = isGroup ? 'Y' : 'N';
        }

        if (groupPanel) {
            groupPanel.style.display = isGroup ? 'block' : 'none';
        }
    }

    function setInputRowValues(row, inputData) {
        (row.querySelector('.js-inp-code') || {}).value = inputData.code || '';
        (row.querySelector('.js-inp-min') || {}).value = inputData.min || '';
        (row.querySelector('.js-inp-max') || {}).value = inputData.max || '';
        (row.querySelector('.js-inp-step') || {}).value = inputData.step || '';
        (row.querySelector('.js-inp-unit') || {}).value = inputData.unit || '';
    }

    function tryApplySavedSchema() {
        if (!schemaInput.value) {
            document.querySelectorAll('.fc-card').forEach(syncGroupState);
            return;
        }

        let parsed = null;
        try {
            parsed = JSON.parse(schemaInput.value);
        } catch (e) {
            document.querySelectorAll('.fc-card').forEach(syncGroupState);
            return;
        }

        const fields = parsed && Array.isArray(parsed.fields) ? parsed.fields : [];
        const byCode = {};
        fields.forEach(function(field) {
            if (field && field.property_code) {
                byCode[field.property_code] = field;
            }
        });

        document.querySelectorAll('.fc-card').forEach(function(card) {
            const propertyCode = card.dataset.propCode || '';
            const field = byCode[propertyCode];

            if (!field) {
                syncGroupState(card);
                return;
            }

            if (card.querySelector('.js-show-presets')) {
                card.querySelector('.js-show-presets').checked = !!field.show_presets;
            }
            if (card.querySelector('.js-show-unit')) {
                card.querySelector('.js-show-unit').checked = !!field.show_unit;
            }
            if (card.querySelector('.js-concat-unit')) {
                card.querySelector('.js-concat-unit').checked = !!field.concat_unit;
            }
            if (card.querySelector('.js-group-code')) {
                card.querySelector('.js-group-code').value = field.group_code || '';
            }
            if (card.querySelector('.js-group-delimiter')) {
                card.querySelector('.js-group-delimiter').value = field.group_delimiter || 'x';
            }

            const inputs = Array.isArray(field.inputs) && field.inputs.length ? field.inputs : [];
            if (!inputs.length) {
                syncGroupState(card);
                return;
            }

            const container = card.querySelector('.js-fc-inputs');
            const baseRow = card.querySelector('.js-fc-input-row');
            if (!container || !baseRow) {
                syncGroupState(card);
                return;
            }

            container.innerHTML = '';
            inputs.forEach(function(input, index) {
                const row = index === 0 ? baseRow : baseRow.cloneNode(true);
                setInputRowValues(row, input || {});
                container.appendChild(row);
            });

            syncGroupState(card);
        });
    }
})();
</script>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>

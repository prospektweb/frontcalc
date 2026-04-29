# prospektweb.frontcalc

Минимальный baseline-модуль Bitrix (установка/удаление/настройки).

## Установка
1. Скопировать папку модуля в `/local/modules/prospektweb.frontcalc/`
2. В админке: Marketplace → Установленные решения → установить `prospektweb.frontcalc`
3. Проверить настройки: `/bitrix/admin/settings.php?mid=prospektweb.frontcalc`

## Что делает baseline
- Регистрирует модуль
- Проверяет зависимости `iblock`, `catalog`, `sale`
- Автоопределяет `PRODUCTS_IBLOCK_ID` и `OFFERS_IBLOCK_ID` и сохраняет в `Option`
- Корректно удаляется с выбором: удалять или сохранять данные

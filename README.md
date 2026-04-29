# prospektweb.frontcalc

Минимальный baseline-модуль Bitrix (установка/удаление/настройки).

## Установка
1. Скопировать папку модуля в `/local/modules/prospektweb.frontcalc/`
2. В админке: Marketplace → Установленные решения → установить `prospektweb.frontcalc`
3. Проверить настройки: `/bitrix/admin/settings.php?mid=prospektweb.frontcalc`

## Поддерживаемая политика расположения файлов
- Поддерживается **только один корень модуля**: `/local/modules/prospektweb.frontcalc`.
- Режим с частичным разнесением файлов между `/local/modules/prospektweb.frontcalc` и `/bitrix/modules/prospektweb.frontcalc` **запрещён**.
- Инсталлятор валидирует целостность критичных файлов в `/local/modules/prospektweb.frontcalc` до создания admin/ajax-прокси.

## Что делает baseline
- Регистрирует модуль
- Проверяет зависимости `iblock`, `catalog`, `sale`
- Автоопределяет `PRODUCTS_IBLOCK_ID` и `OFFERS_IBLOCK_ID` и сохраняет в `Option`
- Корректно удаляется с выбором: удалять или сохранять данные

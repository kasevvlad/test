# Обзор схемы `orders` / `orders_article`

Разбор проблем консервативной MySQL/MariaDB-схемы `orders` + `orders_article` и улучшенный дамп (типы, constraints, индексы, FK — без изменения набора таблиц).

## 1. Деньги и количества хранятся как `double`

`delivery`, `cur_rate`, `delivery_price_euro`, `weight_gross` в `orders`, и `amount`, `price`, `price_eur`, `weight`, `packaging_count`, `pallet`, `packaging` в `orders_article` — всё `double` (бинарная плавающая точка). Для денег это классическая и опасная ошибка: `0.1 + 0.2 ≠ 0.3` в IEEE 754, округления накапливаются, суммы по заказу могут не сходиться с суммой позиций на копейки — и это всплывает случайно, на конкретных суммах, а не всегда. Нужен `DECIMAL(10,2)` для денег, `DECIMAL` с нужной точностью для количеств/веса.

## 2. Нет ни одного FOREIGN KEY

`user_id`, `delivery_country`, `address_payer` в `orders`, `orders_id` и `article_id` в `orders_article` — все "как бы ссылки", но без реальных FK. Это значит: БД не гарантирует, что `orders_article.orders_id` вообще существует в `orders`; удаление пользователя/статьи каталога может молча оставить сиротские строки; ON DELETE поведение целиком на совести приложения (и любого прямого доступа к БД в обход приложения).

## 3. "Уникальные" поля без UNIQUE-индекса

`token` — комментарий буквально говорит "уникальный хеш пользователя", но ни unique, ни вообще какого-либо индекса на нём нет. `hash` (`IDX_5`) и `number` (номер заказа, наверняка используется для поиска клиентом/поддержкой) — та же история: если это публичные идентификаторы для доступа к заказу, отсутствие уникальности — это не только про производительность, а про потенциальную коллизию/утечку чужого заказа по чужому хешу.

## 4. Несогласованность между таблицами

`orders.measure` — `varchar(3)`, `orders_article.measure` — `varchar(2)`. Одно и то же концептуально поле, разная длина в двух таблицах — при обычном копировании значения из `orders` в `orders_article` рискуете получить усечение на длинных кодах единиц измерения.

## 5. Nullable-булевы с DEFAULT — путаница NULL vs false

`address_equal tinyint(1) default 1 null`, `payment_euro tinyint(1) default 0 null`, `delivery_calculate_type smallint default 0 null` — если поле реально бинарное и есть DEFAULT, NULL как отдельное состояние скорее всего никогда осмысленно не используется, а просто оставляет дыру ("а что если кто-то явно запишет NULL?"). Либо это NOT NULL с default, либо NULL — это осознанное третье состояние ("не спрашивали"), и тогда это нужно явно документировать, а не оставлять как есть. Часть полей (`bank_transfer_requested`, `accept_pay`, `product_review`, `process`, `spec_price`, `show_msg`) — nullable без default, тут тройное состояние выглядит осмысленнее, их не трогаю.

## 6. "Магические числа" без валидации на уровне БД

`status`, `vat_type`, `delivery_type`, `offset_reason`, `pay_type`, `sex`, `delivery_calculate_type`, `multiple_pallet` — их смысл существует только в комментарии/коде приложения, БД примет любое целое число. Полноценное решение — таблицы-справочники (`order_statuses` и т.п.), но это уже не "консервативно" — добавлен `CHECK` там, где диапазон известен из комментария (например `vat_type IN (0,1)`), и оставлена заметка, где диапазон не очевиден.

## 7. `update_date` не обновляется автоматически

`update_date datetime null` без `ON UPDATE CURRENT_TIMESTAMP` — целиком полагается на то, что каждый UPDATE в приложении не забудет его проставить. Один забытый путь обновления (миграция данных, ручной фикс, новый сервис) — и `update_date` начинает врать. Добавлен `ON UPDATE CURRENT_TIMESTAMP`, и `DEFAULT CURRENT_TIMESTAMP` для `create_date`.

## 8. `warehouse_data longtext` и `bank_details longtext` — неструктурированные блобы

`warehouse_data` хранит "адрес, название, часы работы" одной строкой — ни провалидировать, ни выбрать одно поле SQL-запросом. Переведён в `JSON` (MySQL 5.7.8+/8.0) — минимальное изменение, но теперь хотя бы валидный JSON и доступны `JSON_EXTRACT`. `bank_details` — реквизиты банка для возврата средств, это чувствительные финансовые данные в открытом виде в БД; тип столбца тут не главная проблема — рекомендуется шифрование на уровне приложения (или вынос в отдельный сервис/vault), DDL это не решает, оставлено как есть с пометкой.

## 9. Отсутствие charset/engine в DDL

Оригинал не указывает `ENGINE`/`CHARSET` явно — для FK обязателен `InnoDB` (в `MyISAM` внешние ключи не работают вообще), а без явного `utf8mb4` есть риск того, что таблица создастся в чём-то вроде `latin1`/`utf8mb3` по дефолтам сервера — и тогда часть имён клиентов/адресов на не-латинице (включая эмодзи, редкие символы) будет биться или обрезаться.

## Что не тронуто (вне рамок "консервативно")

- 8 колонок `delivery_time_*` (min/max × 4 варианта) и ещё 9 колонок дат жизненного цикла заказа (`offset_date`, `proposed_date`, `ship_date`, `sending_date`, `cancel_date`, `fact_date`, `full_payment_date`, `pay_date_execution`) — по-хорошему это отдельная таблица истории/оценок доставки (`order_delivery_estimates`, `order_events`), а не десяток nullable-колонок на одной строке. Наличие `delivery_old_time_min/max` ("прошлый срок") — прямой симптом того, что эта модель уже не справляется без ручного дублирования колонок под "предыдущее значение".
- Адрес доставки инлайнится прямо в `orders` (`delivery_index/country/region/city/address/building/phone*`), а адрес плательщика уже вынесен в отдельную сущность (`address_payer` — FK-подобное поле). Это несогласованность: то же самое обращение с delivery-адресом (`addresses` + `delivery_address_id`) сделало бы схему консистентной и переиспользуемой.
- `manager_name/email/phone`, `carrier_name/carrier_contact_data` — те же контактные данные инлайнятся вместо FK на `managers`/`carriers`. Может быть намеренным снепшотом "на момент заказа" — тогда это нормально, но тогда стоит явно завести отдельные live-FK поля для отчётности, а снепшот-поля переименовать в `*_snapshot`.
- `orders_article.article_id` не хранит снепшот названия/SKU артикула — если каталожная позиция удалится или переименуется, история заказа частично теряет смысл (цена сохранена, а что именно купили — не всегда).

Всё это — не "баг", а архитектурное решение, которое стоит явно обсудить с командой, прежде чем менять: это уже не патч типов, а миграция данных в новые таблицы.

## Улучшенный дамп

Предположены названия связанных таблиц (`users`, `countries`, `addresses`, `articles`) — поправьте под реальные, если отличаются. Нужен MySQL 8.0.16+ / MariaDB 10.2+ для `CHECK`.

```sql
CREATE TABLE orders
(
    id                         int auto_increment primary key,
    hash                       varchar(32)              not null comment 'hash заказа',
    user_id                    int                      null,
    token                      varchar(64)              not null comment 'уникальный хеш пользователя',
    number                     varchar(10)              null comment 'Номер заказа',
    status                     int        default 1     not null comment 'Статус заказа',
    email                      varchar(100)             null comment 'контактный E-mail',
    vat_type                   int        default 0     not null comment 'Частное лицо (0) или плательщик НДС (1)',
    vat_number                 varchar(100)             null comment 'НДС-номер',
    tax_number                 varchar(50)              null comment 'Индивидуальный налоговый номер налогоплательщика',
    discount                   smallint                 null comment 'Процент скидки (0-100)',
    delivery                   decimal(10, 2)           null comment 'Стоимость доставки',
    delivery_type              smallint   default 0     null comment 'Тип доставки: 0 - адрес клиента, 1 - адрес склада',
    delivery_time_min          date                     null comment 'Минимальный срок доставки',
    delivery_time_max          date                     null comment 'Максимальный срок доставки',
    delivery_time_confirm_min  date                     null comment 'Минимальный срок доставки подтверждённый производителем',
    delivery_time_confirm_max  date                     null comment 'Максимальный срок доставки подтверждённый производителем',
    delivery_time_fast_pay_min date                     null comment 'Минимальный срок доставки',
    delivery_time_fast_pay_max date                     null comment 'Максимальный срок доставки',
    delivery_old_time_min      date                     null comment 'Прошлый минимальный срок доставки',
    delivery_old_time_max      date                     null comment 'Прошлый максимальный срок доставки',
    delivery_index             varchar(20)              null,
    delivery_country           int                      null,
    delivery_region            varchar(50)              null,
    delivery_city              varchar(200)             null,
    delivery_address           varchar(300)             null,
    delivery_building          varchar(200)             null,
    delivery_phone_code        varchar(20)              null,
    delivery_phone             varchar(20)              null,
    sex                        smallint                 null comment 'Пол клиента',
    client_name                varchar(255)             null comment 'Имя клиента',
    client_surname             varchar(255)             null comment 'Фамилия клиента',
    company_name               varchar(255)             null comment 'Название компании',
    pay_type                   smallint                 not null comment 'Выбранный тип оплаты',
    pay_date_execution         datetime                 null comment 'Дата до которой действует текущая цена заказа',
    offset_date                datetime                 null comment 'Дата сдвига предполагаемого расчета доставки',
    offset_reason              smallint                 null comment 'причина сдвига сроков: 1 - каникулы на фабрике, 2 - фабрика уточняет сроки пр-ва, 3 - другое',
    proposed_date               datetime                 null comment 'Предполагаемая дата поставки',
    ship_date                  datetime                 null comment 'Предполагаемая дата отгрузки',
    tracking_number            varchar(50)              null comment 'Номер треккинга',
    manager_name               varchar(20)              null comment 'Имя менеджера сопровождающего заказ',
    manager_email              varchar(30)              null comment 'Email менеджера сопровождающего заказ',
    manager_phone              varchar(20)              null comment 'Телефон менеджера сопровождающего заказ',
    carrier_name               varchar(50)              null comment 'Название транспортной компании',
    carrier_contact_data       varchar(255)             null comment 'Контактные данные транспортной компании',
    locale                     varchar(5)               not null comment 'локаль из которой был оформлен заказ',
    cur_rate                   decimal(10, 4) default 1 null comment 'курс на момент оплаты',
    currency                   char(3)    default 'EUR' not null comment 'валюта при которой был оформлен заказ',
    measure                    varchar(3) default 'm'   not null comment 'ед. изм. в которой был оформлен заказ',
    name                       varchar(200)             not null comment 'Название заказа',
    description                varchar(1000)            null comment 'Дополнительная информация',
    create_date                datetime   default current_timestamp not null comment 'Дата создания',
    update_date                datetime   default current_timestamp on update current_timestamp null comment 'Дата изменения',
    warehouse_data              json                     null comment 'Данные склада: адрес, название, часы работы',
    step                       smallint   default 1     not null comment 'шаг оформления заказа (уточнить у команды: комментарий "если true" не соответствует типу smallint)',
    address_equal               tinyint(1) default 1     not null comment 'Адреса плательщика и получателя совпадают (0 - разные, 1 - одинаковые)',
    bank_transfer_requested    tinyint(1)               null comment 'Запрашивался ли счет на банковский перевод (NULL - не запрашивался/неизвестно)',
    accept_pay                 tinyint(1)               null comment 'Если true то заказ отправлен в работу',
    cancel_date                datetime                 null comment 'Конечная дата согласования сроков поставки',
    weight_gross               decimal(10, 3)           null comment 'Общий вес брутто заказа',
    product_review             tinyint(1)               null comment 'Оставлен отзыв по коллекциям в заказе',
    mirror                     smallint                 null comment 'Метка зеркала на котором создается заказ',
    process                    tinyint(1)               null comment 'метка массовой обработки',
    fact_date                  datetime                 null comment 'Фактическая дата поставки',
    entrance_review            smallint                 null comment 'Фиксирует вход клиента на страницу отзыва и последующие клики',
    payment_euro                tinyint(1) default 0     not null comment 'Если true, то оплату посчитать в евро',
    spec_price                 tinyint(1)               null comment 'установлена спец цена по заказу',
    show_msg                   tinyint(1)               null comment 'Показывать спец. сообщение',
    delivery_price_euro        decimal(10, 2)           null comment 'Стоимость доставки в евро',
    address_payer              int                      null,
    sending_date                datetime                 null comment 'Расчетная дата поставки',
    delivery_calculate_type     smallint   default 0     not null comment 'Тип расчета: 0 - ручной, 1 - автоматический',
    full_payment_date          date                     null comment 'Дата полной оплаты заказа',
    bank_details                longtext                 null comment 'Реквизиты банка для возврата средств (хранить в зашифрованном виде на уровне приложения)',
    delivery_apartment_office   varchar(30)              null comment 'Квартира/офис',

    constraint chk_orders_status_positive check (status > 0),
    constraint chk_orders_vat_type check (vat_type in (0, 1)),
    constraint chk_orders_vat_number_requires_payer check (vat_type = 1 or vat_number is null),
    constraint chk_orders_discount_range check (discount is null or discount between 0 and 100),
    constraint chk_orders_delivery_type check (delivery_type is null or delivery_type in (0, 1)),
    constraint chk_orders_sex check (sex is null or sex in (0, 1, 2)),
    constraint chk_orders_pay_type_positive check (pay_type > 0),
    constraint chk_orders_offset_reason check (offset_reason is null or offset_reason between 1 and 3),
    constraint chk_orders_delivery_calculate_type check (delivery_calculate_type in (0, 1)),
    constraint chk_orders_delivery_time_range check (delivery_time_max is null or delivery_time_min is null or delivery_time_max >= delivery_time_min),
    constraint chk_orders_delivery_time_confirm_range check (delivery_time_confirm_max is null or delivery_time_confirm_min is null or delivery_time_confirm_max >= delivery_time_confirm_min),
    constraint chk_orders_delivery_time_fast_pay_range check (delivery_time_fast_pay_max is null or delivery_time_fast_pay_min is null or delivery_time_fast_pay_max >= delivery_time_fast_pay_min),
    constraint chk_orders_delivery_old_time_range check (delivery_old_time_max is null or delivery_old_time_min is null or delivery_old_time_max >= delivery_old_time_min)
)
    engine = InnoDB
    default charset = utf8mb4
    collate = utf8mb4_0900_ai_ci
    comment 'Хранит информацию о заказах';

create unique index uniq_orders_hash on orders (hash);
create unique index uniq_orders_token on orders (token);
create unique index uniq_orders_number on orders (number);
create index idx_orders_delivery_country on orders (delivery_country);
create index idx_orders_user_id on orders (user_id);
create index idx_orders_create_date on orders (create_date);
create index idx_orders_create_date_status on orders (create_date, status);
create index idx_orders_status on orders (status);
create index idx_orders_email on orders (email);
create index idx_orders_address_payer on orders (address_payer);

alter table orders
    add constraint fk_orders_user foreign key (user_id) references users (id) on delete set null,
    add constraint fk_orders_delivery_country foreign key (delivery_country) references countries (id) on delete restrict,
    add constraint fk_orders_address_payer foreign key (address_payer) references addresses (id) on delete set null;

create table orders_article
(
    id                        int auto_increment primary key,
    orders_id                 int                  not null,
    article_id                int                  null comment 'ID коллекции',
    amount                    decimal(10, 3)       not null comment 'количество артикулов в ед. измерения',
    price                     decimal(10, 2)       not null comment 'Цена на момент оплаты заказа',
    price_eur                 decimal(10, 2)       null comment 'Цена в Евро по заказу',
    currency                  char(3)              null comment 'Валюта для которой установлена цена',
    measure                   varchar(3)           null comment 'Ед. изм. для которой установлена цена',
    delivery_time_min         date                 null comment 'Минимальный срок доставки',
    delivery_time_max         date                 null comment 'Максимальный срок доставки',
    weight                    decimal(10, 3)       not null comment 'вес упаковки',
    multiple_pallet           smallint             null comment 'Кратность палете: 1 - кратно упаковке, 2 - кратно палете, 3 - не меньше палеты',
    packaging_count           decimal(10, 3)       not null comment 'Количество кратно которому можно добавлять товар в заказ',
    pallet                    decimal(10, 3)       not null comment 'количество в палете на момент заказа',
    packaging                 decimal(10, 3)       not null comment 'количество в упаковке',
    swimming_pool             tinyint(1) default 0 not null comment 'Плитка специально для бассейна',

    constraint chk_orders_article_amount_positive check (amount > 0),
    constraint chk_orders_article_price_non_negative check (price >= 0),
    constraint chk_orders_article_price_eur_non_negative check (price_eur is null or price_eur >= 0),
    constraint chk_orders_article_weight_non_negative check (weight >= 0),
    constraint chk_orders_article_packaging_count_positive check (packaging_count > 0),
    constraint chk_orders_article_pallet_positive check (pallet > 0),
    constraint chk_orders_article_packaging_positive check (packaging > 0),
    constraint chk_orders_article_multiple_pallet check (multiple_pallet is null or multiple_pallet in (1, 2, 3)),
    constraint chk_orders_article_delivery_time_range check (delivery_time_max is null or delivery_time_min is null or delivery_time_max >= delivery_time_min)
)
    engine = InnoDB
    default charset = utf8mb4
    collate = utf8mb4_0900_ai_ci
    comment 'Хранит информацию об артикулах заказа';

create index idx_orders_article_article_id on orders_article (article_id);
create index idx_orders_article_orders_id on orders_article (orders_id);

alter table orders_article
    add constraint fk_orders_article_order foreign key (orders_id) references orders (id) on delete cascade,
    add constraint fk_orders_article_article foreign key (article_id) references articles (id) on delete set null;
```

## Предположения, которые стоит перепроверить перед прогоном на реальных данных

- Диапазоны `sex` / `offset_reason` / `pay_type`.
- Реальные названия связанных таблиц (`users`, `countries`, `addresses`, `articles`).
- То, действительно ли `address_equal` / `payment_euro` / `delivery_calculate_type` никогда не должны быть `NULL` — они сделаны `NOT NULL`; если в проде там реально есть строки с `NULL`, `ALTER TABLE` на это упадёт, и сначала нужно будет проставить им `0`/дефолтное значение.

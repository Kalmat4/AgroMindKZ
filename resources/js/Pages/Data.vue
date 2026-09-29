<script setup>
// Публичная справка: какие данные показывает AgroMind KZ, откуда они и где хранятся.
// Цифры порогов совпадают с кодом: FireDanger.php, FieldThreatService.php,
// OpenWeatherMapService.php, NasaFirmsService.php — при их изменении править и здесь.
import { Head, Link } from '@inertiajs/vue3'

const SECTIONS = [
    ['firms', 'Термоточки NASA FIRMS'],
    ['stubble', 'Пал стерни или пожар'],
    ['weather', 'Прогноз погоды'],
    ['risks', 'Угрозы для урожая'],
    ['fire-danger', 'Пожароопасность погоды'],
    ['fields', 'Мои поля и уровень угрозы'],
    ['damage', 'Урожай под угрозой, ₸'],
    ['alerts', 'Тревоги в Telegram'],
    ['ai', 'ИИ-агроном'],
    ['map', 'Карта и границы'],
    ['storage', 'Где хранятся данные'],
]
</script>

<template>
    <Head title="Данные и источники — AgroMind KZ" />

    <div class="ds-root">
        <header class="ds-hero">
            <div class="ds-inner">
                <Link href="/about" class="ds-back">← AgroMind KZ</Link>
                <h1>Данные и источники</h1>
                <p class="ds-lead">
                    Что показывает платформа, откуда берутся цифры, как мы их обрабатываем и где они хранятся.
                    Все внешние источники открытые; собственные расчёты помечены как «наш расчёт».
                </p>
                <nav class="ds-toc">
                    <a v-for="[id, title] in SECTIONS" :key="id" :href="'#' + id">{{ title }}</a>
                </nav>
            </div>
        </header>

        <main class="ds-inner ds-main">

            <!-- Сводка -->
            <section class="ds-card">
                <h2>Коротко</h2>
                <div class="ds-table-wrap">
                    <table class="ds-table">
                        <thead>
                            <tr><th>Данные</th><th>Источник</th><th>Где на сайте</th><th>Свежесть</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Термоточки (возможные пожары)</td><td>NASA FIRMS, спутник Suomi NPP (VIIRS)</td><td>Карта → «Пожары», «Мои поля»</td><td>за 48 ч, обновление каждые 10 мин</td></tr>
                            <tr><td>Погода: температура, осадки, ветер, влажность</td><td>OpenWeatherMap, прогноз 5 дней</td><td>«Прогноз угроз», «Мои поля»</td><td>обновление каждые 30 мин</td></tr>
                            <tr><td>Пожароопасность погоды</td><td>наш расчёт по прогнозу OpenWeatherMap</td><td>«Прогноз угроз», «Мои поля», Telegram</td><td>вместе с прогнозом</td></tr>
                            <tr><td>Угрозы для урожая</td><td>наш расчёт по прогнозу и термоточкам</td><td>«Прогноз угроз»</td><td>вместе с прогнозом</td></tr>
                            <tr><td>Уровень угрозы поля, время подхода огня</td><td>наш расчёт: термоточки + ветер</td><td>«Мои поля», Telegram</td><td>при открытии и каждые 30 мин</td></tr>
                            <tr><td>Урожай под угрозой, ₸</td><td>урожайность Бюро нацстатистики РК × цена фермера</td><td>«Мои поля», Telegram</td><td>статистика за последний год</td></tr>
                            <tr><td>Ответы ИИ-агронома</td><td>Claude (Anthropic) через n8n</td><td>«ИИ Помощник агроному»</td><td>на каждый вопрос</td></tr>
                            <tr><td>Подложка карты</td><td>OpenStreetMap</td><td>карты</td><td>—</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- FIRMS -->
            <section id="firms" class="ds-card">
                <h2>🔥 Термоточки NASA FIRMS</h2>
                <p>
                    <b>Что это.</b> Спутник <b>Suomi NPP</b> (NASA/NOAA) с прибором <b>VIIRS</b> снимает Землю в инфракрасном диапазоне
                    пикселями ~375 × 375 м и отмечает пиксели, которые заметно горячее окружения. Такие пиксели — <b>термоточки</b>:
                    чаще всего это огонь (пожар, пал стерни), реже — факел, горячая крыша, промышленный объект.
                    Термоточка — <b>не подтверждённый пожар</b>. Под облаками спутник огонь не видит.
                </p>
                <p>
                    <b>Откуда.</b> Сервис NASA FIRMS (Fire Information for Resource Management System), продукт VIIRS_SNPP_NRT
                    — данные почти в реальном времени, доступны через ~3 часа после пролёта. Спутник проходит над Казахстаном
                    днём и ночью. Мы запрашиваем термоточки за последние <b>48 часов</b> в границах области или вокруг поля
                    и держим ответ в кэше 10 минут.
                </p>
                <h3>Поля во всплывающей карточке</h3>
                <dl class="ds-dl">
                    <dt>Снимок</dt>
                    <dd>Время пролёта спутника. Показываем по Астане (UTC+5), в скобках — UTC, как в исходных данных NASA.</dd>
                    <dt>Мощность огня (FRP)</dt>
                    <dd>
                        Fire Radiative Power — сколько энергии огонь излучает в секунду, в мегаваттах. Лучший показатель интенсивности:
                        чем больше FRP, тем больше горит биомассы. Ориентиры: единицы МВт — небольшой очаг или пал стерни;
                        ~10 МВт — заметный пожар; сотни МВт — крупный пожар.
                    </dd>
                    <dt>Нагрев пикселя, K</dt>
                    <dd>
                        Радиояркостная температура пикселя в канале 3,7 мкм (bright_ti4), в кельвинах. Это <b>средняя температура
                        всего пикселя 375 м</b>, а не температура пламени: в пиксель попадают и огонь, и холодная земля.
                        Фон степи — примерно 290–310 K; 340–360 K означает уверенный источник жара. Канал насыщается около 367 K,
                        поэтому силу огня лучше оценивать по FRP.
                    </dd>
                    <dt>Достоверность</dt>
                    <dd>Оценка NASA, что это действительно огонь: низкая (l), средняя (n), высокая (h).</dd>
                    <dt>Спутник и пролёт</dt>
                    <dd>Suomi NPP (код N); дневной или ночной снимок. Ночью огонь виден контрастнее.</dd>
                </dl>
                <h3>Степень очага — наш расчёт</h3>
                <table class="ds-table ds-table--compact">
                    <thead><tr><th>Степень</th><th>Условие</th></tr></thead>
                    <tbody>
                        <tr><td>Высокий</td><td>FRP ≥ 25 МВт или нагрев ≥ 400 K</td></tr>
                        <tr><td>Умеренный</td><td>FRP ≥ 8 МВт или нагрев ≥ 340 K</td></tr>
                        <tr><td>Слабый</td><td>остальные</td></tr>
                    </tbody>
                </table>
            </section>

            <!-- Пал стерни -->
            <section id="stubble" class="ds-card">
                <h2>🌾 Пал стерни или пожар — наш расчёт</h2>
                <p>
                    Со спутника пал стерни и пожар выглядят одинаково — горячий пиксель 375 м. Поэтому мы не утверждаем, а
                    оцениваем по признакам. Метку <b>«вероятный пал стерни»</b> точка получает, только если выполнены все четыре условия:
                </p>
                <table class="ds-table ds-table--compact">
                    <thead><tr><th>Признак</th><th>Условие</th><th>Откуда</th></tr></thead>
                    <tbody>
                        <tr><td>Под точкой пашня</td><td>класс «пашня» на карте земель</td><td>ESA WorldCover 2021, пиксель ~74 м</td></tr>
                        <tr><td>Сезон палов</td><td>март–май (перед севом) или август–ноябрь (после уборки)</td><td>дата снимка</td></tr>
                        <tr><td>Небольшой огонь</td><td>мощность очага до 20 МВт (по всем точкам в 1,5 км)</td><td>NASA FIRMS, FRP</td></tr>
                        <tr><td>Не держится</td><td>точки в 1,5 км не повторяются в пролётах спутника с разницей 3 ч и больше</td><td>NASA FIRMS, время снимков</td></tr>
                    </tbody>
                </table>
                <p>
                    В остальных случаях точка остаётся <b>«возможным пожаром»</b>. Если термоточка попала внутрь поля пользователя
                    (круг той же площади вокруг точки поля), она помечается <b>«на вашем поле»</b> и тревогу не вызывает —
                    вероятно, это пал самого хозяина. Пал на соседней пашне тревогу вызывает: палы часто уходят в степь.
                </p>
                <p class="ds-note">
                    Это оценка, а не факт: карта земель 2021 года, а поля и пастбища меняются. Сжигание стерни и неконтролируемые
                    палы — одна из частых причин степных пожаров. Данные о земле: © ESA WorldCover project 2021 / Contains modified
                    Copernicus Sentinel data (2021) processed by ESA WorldCover consortium, лицензия CC BY 4.0.
                </p>
            </section>

            <!-- Погода -->
            <section id="weather" class="ds-card">
                <h2>⛅ Прогноз погоды</h2>
                <p>
                    <b>Откуда.</b> OpenWeatherMap, прогноз на 5 дней с шагом 3 часа (40 точек прогноза). Кэш 30 минут.
                </p>
                <p>
                    <b>Для какой точки.</b> В слое «Прогноз угроз» — для <b>центра области</b>: это обзор, внутри большой области
                    погода может отличаться. В «Моих полях» и в Telegram — <b>точно по координатам поля</b>.
                </p>
                <dl class="ds-dl">
                    <dt>Темп.</dt><dd>Минимум и максимум среди точек прогноза на 5 дней, °C.</dd>
                    <dt>Осадки</dt><dd>Сумма дождя и снега за 5 дней, мм.</dd>
                    <dt>Порывы до</dt><dd>Максимальный порыв ветра за 5 дней, м/с. Не путать со средним ветром.</dd>
                    <dt>Ветер (в «Моих полях»)</dt><dd>Средняя скорость и направление ветра в ближайший срок прогноза. Направление указано «откуда дует».</dd>
                    <dt>Влажность</dt><dd>Относительная влажность воздуха, %, — используется в расчёте пожароопасности.</dd>
                </dl>
            </section>

            <!-- Угрозы -->
            <section id="risks" class="ds-card">
                <h2>⚠️ Угрозы для урожая — наш расчёт</h2>
                <p>Правила применяются к прогнозу на 5 дней. Из угроз одного типа показывается самая сильная.</p>
                <div class="ds-table-wrap">
                    <table class="ds-table ds-table--compact">
                        <thead><tr><th>Угроза</th><th>Средний уровень</th><th>Высокий уровень</th></tr></thead>
                        <tbody>
                            <tr><td>🌧️ Сильный дождь</td><td>> 15 мм за 3 ч</td><td>> 30 мм за 3 ч</td></tr>
                            <tr><td>🌨️ Град / гроза</td><td>гроза с моросью</td><td>гроза, в том числе с ливнем</td></tr>
                            <tr><td>💨 Сильный ветер</td><td>порывы > 15 м/с</td><td>порывы > 25 м/с</td></tr>
                            <tr><td>🏜️ Засуха</td><td>осадки &lt; 2 мм за 5 дней и жара > 30 °C</td><td>то же и > 35 °C</td></tr>
                            <tr><td>❄️ Заморозки</td><td>минимум &lt; 2 °C</td><td>минимум &lt; −2 °C</td></tr>
                            <tr><td>🌡️ Пожароопасная погода</td><td>пожароопасность «высокая»</td><td>«чрезвычайная»</td></tr>
                            <tr><td>🔥 Пожары</td><td>от 4 термоточек в области за 48 ч (1–3 — низкий)</td><td>от 10 термоточек</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- HDW -->
            <section id="fire-danger" class="ds-card">
                <h2>🌡️ Пожароопасность погоды — наш расчёт</h2>
                <p>
                    Показывает, насколько погода помогает огню разгореться и распространиться. Считаем по индексу
                    <b>Hot-Dry-Windy</b> (Srock et al., 2018), применяемому в прогнозах пожарной погоды:
                </p>
                <p class="ds-formula">HDW = дефицит влажности воздуха (гПа) × скорость ветра (м/с)</p>
                <p>
                    Дефицит влажности — насколько воздух далёк от насыщения; растёт с жарой и сухостью. Берём максимум индекса
                    по прогнозу на ближайшие <b>48 часов</b>. Если за это время ожидается ≥ 5 мм осадков — уровень снижается на ступень.
                </p>
                <table class="ds-table ds-table--compact">
                    <thead><tr><th>Индекс HDW</th><th>Уровень</th></tr></thead>
                    <tbody>
                        <tr><td>до 50</td><td>низкая</td></tr>
                        <tr><td>50–120</td><td>умеренная</td></tr>
                        <tr><td>120–250</td><td>высокая</td></tr>
                        <tr><td>больше 250</td><td>чрезвычайная</td></tr>
                    </tbody>
                </table>
                <p class="ds-note">
                    Пример: +30 °C, влажность 20 %, ветер 8 м/с → HDW ≈ 270, «чрезвычайная». Пороги подобраны нами под степь.
                    Это оценка погодных условий, а не официальный класс пожарной опасности (в РК — индекс Нестерова, для него нужна
                    история осадков; в планах).
                </p>
            </section>

            <!-- Поля -->
            <section id="fields" class="ds-card">
                <h2>🌾 Мои поля и уровень угрозы — наш расчёт</h2>
                <p>
                    Фермер отмечает точку поля на карте и указывает площадь, культуру и цену урожая за тонну.
                    Для каждого поля берём термоточки NASA FIRMS в радиусе <b>50 км</b> за 48 часов и прогноз погоды по координатам поля.
                </p>
                <dl class="ds-dl">
                    <dt>Расстояние и сторона</dt><dd>По прямой от поля до термоточки; сторона света — где очаг относительно поля.</dd>
                    <dt>Ветер на поле</dt><dd>Ветер дует от очага в сторону поля с отклонением не больше ±45°.</dd>
                    <dt>Время подхода огня</dt>
                    <dd>Только при ветре на поле: расстояние ÷ скорость огня, где скорость огня ≈ 10 % скорости ветра
                        (правило Cruz &amp; Alexander, 2019). Это <b>грубая оценка</b> порядка величины — «часы или сутки», а не точный прогноз.</dd>
                </dl>
                <table class="ds-table ds-table--compact">
                    <thead><tr><th>Уровень</th><th>Когда</th></tr></thead>
                    <tbody>
                        <tr><td>🚨 Критическая угроза</td><td>очаг ближе 5 км, или ближе 20 км и ветер на поле</td></tr>
                        <tr><td>🔥 Высокая угроза</td><td>очаг ближе 20 км, или ветер на поле при высокой/чрезвычайной пожароопасности</td></tr>
                        <tr><td>👁️ Наблюдение</td><td>есть термоточки в радиусе 50 км</td></tr>
                        <tr><td>✅ Угрозы нет</td><td>термоточек в радиусе 50 км за 48 ч нет</td></tr>
                    </tbody>
                </table>
            </section>

            <!-- Ущерб -->
            <section id="damage" class="ds-card">
                <h2>💰 Урожай под угрозой — наш расчёт</h2>
                <p class="ds-formula">площадь, га × урожайность, ц/га ÷ 10 × цена, ₸/т</p>
                <p>
                    <b>Цену за тонну</b> вводит сам фермер — мы её не подставляем.
                    <b>Урожайность</b> — официальная статистика <b>Бюро национальной статистики АСПиР РК</b>
                    (сборник «Валовый сбор сельскохозяйственных культур. Том III. Урожайность», все категории хозяйств)
                    за последний опубликованный год. Область поля определяется по его координатам через OpenStreetMap:
                    если по области есть данные — берём урожайность области, иначе — в среднем по Казахстану.
                </p>
                <p>
                    <b>ц/га</b> — центнеров с гектара; 1 центнер = 100 кг. Например, 16,0 ц/га = 1,6 т зерна с гектара.
                </p>
                <h3>В среднем по Казахстану, ц/га</h3>
                <div class="ds-table-wrap">
                    <table class="ds-table ds-table--compact">
                        <thead><tr><th>Культура</th><th>2022</th><th>2023</th><th>2024</th><th>2025</th></tr></thead>
                        <tbody>
                            <tr><td>Пшеница яровая</td><td>12,6</td><td>9,0</td><td>14,0</td><td>16,0</td></tr>
                            <tr><td>Ячмень</td><td>15,1</td><td>10,8</td><td>16,8</td><td>15,7</td></tr>
                            <tr><td>Подсолнечник</td><td>12,0</td><td>11,0</td><td>14,6</td><td>13,9</td></tr>
                            <tr><td>Рапс</td><td>14,2</td><td>13,3</td><td>19,2</td><td>19,5</td></tr>
                            <tr><td>Лён масличный (лён-кудряш)</td><td>6,3</td><td>5,0</td><td>8,7</td><td>10,0</td></tr>
                            <tr><td>Гречиха</td><td>7,5</td><td>7,3</td><td>9,5</td><td>9,8</td></tr>
                            <tr><td>Кукуруза на зерно</td><td>58,3</td><td>62,5</td><td>62,0</td><td>57,0</td></tr>
                            <tr><td>Рис</td><td>49,1</td><td>48,5</td><td>52,2</td><td>51,9</td></tr>
                            <tr><td>Картофель</td><td>205,4</td><td>220,1</td><td>219,1</td><td>222,5</td></tr>
                            <tr><td>Овощи открытого грунта</td><td>271,3</td><td>271,9</td><td>284,2</td><td>286,9</td></tr>
                            <tr><td>Сахарная свёкла</td><td>341,4</td><td>379,0</td><td>507,3</td><td>414,3</td></tr>
                        </tbody>
                    </table>
                </div>
                <h3>Пшеница яровая по областям, ц/га</h3>
                <div class="ds-table-wrap">
                    <table class="ds-table ds-table--compact">
                        <thead><tr><th>Область</th><th>2022</th><th>2023</th><th>2024</th><th>2025</th></tr></thead>
                        <tbody>
                            <tr><td>Костанайская</td><td>13,7</td><td>10,5</td><td>13,0</td><td>16,0</td></tr>
                            <tr><td>Акмолинская</td><td>11,5</td><td>6,9</td><td>12,4</td><td>15,4</td></tr>
                            <tr><td>Северо-Казахстанская</td><td>14,7</td><td>11,4</td><td>18,6</td><td>20,7</td></tr>
                            <tr><td>Павлодарская</td><td>9,5</td><td>4,1</td><td>11,2</td><td>13,7</td></tr>
                            <tr><td>Карагандинская</td><td>8,8</td><td>6,8</td><td>14,5</td><td>12,5</td></tr>
                            <tr><td>Актюбинская</td><td>13,6</td><td>9,9</td><td>11,9</td><td>10,7</td></tr>
                            <tr><td>Западно-Казахстанская</td><td>14,1</td><td>9,6</td><td>7,2</td><td>7,6</td></tr>
                            <tr><td>Восточно-Казахстанская</td><td>17,0</td><td>12,1</td><td>22,2</td><td>20,4</td></tr>
                            <tr><td>Абай</td><td>9,8</td><td>10,2</td><td>14,4</td><td>10,0</td></tr>
                            <tr><td>Улытау</td><td>10,0</td><td>8,5</td><td>9,6</td><td>9,2</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="ds-note">
                    По ячменю в платформе тоже есть данные по этим 10 областям. Для остальных областей и культур берётся среднее по стране.
                    Источники по годам:
                    <a href="https://stat.gov.kz/upload/iblock/739/7bauy1hjwwgiib5qsr76avda4xgm9rbz/3-%D1%82%D0%BE%D0%BC%202022%20%D1%80%D1%83%D1%81.xlsx" target="_blank" rel="noopener">2022</a>,
                    <a href="https://stat.gov.kz/api/iblock/element/119388/file/ru/" target="_blank" rel="noopener">2023</a>,
                    <a href="https://stat.gov.kz/api/iblock/element/301856/file/ru/" target="_blank" rel="noopener">2024</a>,
                    <a href="https://stat.gov.kz/api/iblock/element/474891/file/ru/" target="_blank" rel="noopener">2025</a> (stat.gov.kz, xlsx).
                    Данные за 2023 год Бюро пересматривало. Урожайность области не учитывает особенности конкретного поля.
                </p>
            </section>

            <!-- Telegram -->
            <section id="alerts" class="ds-card">
                <h2>📨 Тревоги в Telegram</h2>
                <p>
                    Бот <b>@AgriFireShieldbot</b>. Пользователь подключает его кнопкой «Подключить Telegram» в «Моих полях»
                    (одноразовая ссылка, затем Start в боте). Каждые <b>30 минут</b> сервер пересчитывает угрозу для всех полей
                    и присылает сообщение о каждом <b>новом</b> очаге с уровнем «высокая» или «критическая» — один и тот же очаг
                    повторно не присылается. Кнопка «Проверить и отправить» присылает текущую сводку сразу.
                </p>
                <p>В сообщении: расстояние и сторона очага, время снимка, ветер, время подхода огня, урожай под угрозой, что делать.</p>
            </section>

            <!-- ИИ -->
            <section id="ai" class="ds-card">
                <h2>🤖 ИИ-агроном</h2>
                <p>
                    Отвечает модель <b>Claude</b> (Anthropic) через сервис автоматизации n8n. К вопросу платформа прикладывает
                    контекст: регион или выбранную точку поля, культуру, термоточки NASA FIRMS рядом, прогноз и угрозы
                    OpenWeatherMap, последние сообщения чата. По фото ИИ даёт предварительную оценку состояния посевов —
                    это не лабораторный диагноз.
                </p>
                <p class="ds-note">Данных о почве, влажности почвы и вегетационных индексах (NDVI) у ИИ нет — если их не хватает, он должен об этом сказать.</p>
            </section>

            <!-- Карта -->
            <section id="map" class="ds-card">
                <h2>🗺️ Карта и границы</h2>
                <p>
                    Подложка — <b>OpenStreetMap</b>. Границы областей в платформе — <b>приблизительные прямоугольники</b>
                    (крайние широты и долготы области), а не точные административные границы. Поэтому в счётчик термоточек области
                    могут попасть точки соседних областей или приграничной территории. Для полей расчёт идёт по точке поля и радиусу,
                    без этой погрешности.
                </p>
            </section>

            <!-- Хранение -->
            <section id="storage" class="ds-card">
                <h2>🗄️ Где хранятся данные</h2>
                <p>
                    Спутниковые и погодные данные мы <b>не храним</b> — только держим ответы источников в кэше (10 и 30 минут).
                    Данные пользователей лежат в базе MySQL на сервере платформы:
                </p>
                <div class="ds-table-wrap">
                    <table class="ds-table ds-table--compact">
                        <thead><tr><th>Что</th><th>Таблица</th></tr></thead>
                        <tbody>
                            <tr><td>Аккаунт: имя, email, хеш пароля, привязка Telegram</td><td>users</td></tr>
                            <tr><td>Поля: название, координаты, область, площадь, культура, цена</td><td>fields</td></tr>
                            <tr><td>Какие тревоги уже отправлены</td><td>fire_alerts</td></tr>
                            <tr><td>Регион подписки</td><td>zones</td></tr>
                            <tr><td>Чаты с ИИ-агрономом, включая загруженные фото</td><td>crop_chat_sessions, crop_chat_messages</td></tr>
                            <tr><td>Справочник культур и официальная урожайность по РК и областям</td><td>crops, crop_yields</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="ds-note">
                    Текст вопроса, фото и контекст поля передаются в n8n и Anthropic для получения ответа ИИ.
                    Координаты поля передаются в NASA FIRMS и OpenWeatherMap в виде запроса области и точки, а при создании поля — в OpenStreetMap (Nominatim), чтобы определить область.
                </p>
            </section>
        </main>

        <footer class="ds-footer">AgroMind KZ · 2026 · <Link href="/about">О проекте</Link></footer>
    </div>
</template>

<style scoped>
.ds-root {
    min-height: 100vh;
    background: var(--afs-bg);
    color: var(--afs-text);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
}
.ds-inner { max-width: 960px; margin: 0 auto; padding: 0 20px; }
.ds-hero { padding: 40px 0 24px; border-bottom: 1px solid var(--afs-border); background: var(--afs-bg4); }
.ds-back { color: var(--afs-accent); text-decoration: none; font-size: 14px; }
.ds-hero h1 { font-size: 34px; margin: 12px 0 8px; color: var(--afs-text); }
.ds-lead { color: var(--afs-text2); max-width: 720px; }
.ds-toc { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
.ds-toc a {
    font-size: 13px; padding: 5px 12px; border-radius: 999px; text-decoration: none;
    color: var(--afs-accent); background: var(--afs-dim); border: 1px solid var(--afs-dim);
}
.ds-toc a:hover { border-color: var(--afs-accent); }
.ds-main { display: flex; flex-direction: column; gap: 18px; padding-top: 24px; padding-bottom: 40px; }
.ds-card {
    background: var(--afs-bg3); border: 1px solid var(--afs-border); border-radius: 14px;
    padding: 22px 24px; scroll-margin-top: 16px;
}
.ds-card h2 { font-size: 21px; margin-bottom: 10px; color: var(--afs-text); }
.ds-card h3 { font-size: 15px; margin: 18px 0 8px; color: var(--afs-accent); }
.ds-card p { margin-bottom: 10px; font-size: 15px; }
.ds-card a { color: var(--afs-accent); }
.ds-formula {
    font-family: ui-monospace, Consolas, monospace; font-size: 15px; padding: 10px 14px;
    background: var(--afs-dim2); border-left: 3px solid var(--afs-accent); border-radius: 6px;
}
.ds-note { color: var(--afs-muted); font-size: 13px !important; }
.ds-dl { display: grid; grid-template-columns: 190px 1fr; gap: 8px 16px; font-size: 15px; }
.ds-dl dt { font-weight: 700; color: var(--afs-text); }
.ds-dl dd { color: var(--afs-text2); }
.ds-table-wrap { overflow-x: auto; }
.ds-table { width: 100%; border-collapse: collapse; font-size: 14px; margin: 6px 0 10px; }
.ds-table th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; color: var(--afs-muted); padding: 8px 10px; border-bottom: 1px solid var(--afs-border); }
.ds-table td { padding: 8px 10px; border-bottom: 1px solid var(--afs-border); vertical-align: top; }
.ds-table--compact td, .ds-table--compact th { padding: 6px 10px; }
.ds-footer { text-align: center; padding: 20px; color: var(--afs-muted); font-size: 13px; border-top: 1px solid var(--afs-border); }
.ds-footer a { color: var(--afs-accent); }

@media (max-width: 640px) {
    .ds-hero h1 { font-size: 26px; }
    .ds-card { padding: 16px; }
    .ds-dl { grid-template-columns: 1fr; gap: 2px; }
    .ds-dl dd { margin-bottom: 8px; }
}
</style>

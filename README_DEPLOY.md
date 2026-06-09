# Production-сайт кафе «Вкусненько»

Готовый статический одностраничник для публикации на Netlify, Cloudflare Pages, GitHub Pages или обычном хостинге.

## Что внутри

- `index.html` — главная страница.
- `assets/css/style.css` — стили.
- `assets/js/data.js` — встроенные данные меню и галереи для стабильной работы страницы.
- `assets/js/app.js` — фильтр меню, поиск, галерея, fallback фото.
- `assets/data/menu.json` — меню и цены.
- `assets/data/photos.json` — локальная галерея.
- `assets/img/photos/*.jpg` — фотографии, сохранённые внутри проекта.
- `assets/img/fallback/*.svg` — резервные иллюстрации.
- `manifest.webmanifest`, `robots.txt`, `sitemap.xml`, `_headers`, `netlify.toml`, `404.html`.

## Фото

Все изображения, которые показывает сайт, лежат локально в `assets/img/photos/`.
Внешних hotlink-фото в интерфейсе нет.

## Перед публикацией

1. Замените `https://example.ru/` в `robots.txt` и `sitemap.xml` на реальный домен.
2. Проверьте меню и цены у владельца кафе.
3. При необходимости замените фото в `assets/img/photos/` на авторские фотографии владельца с теми же именами файлов.
4. Загрузите папку на Netlify: Add new project → Deploy manually → перетащить папку.

## Источники данных

- Карточка кафе на Яндекс.Картах: https://yandex.ru/maps/org/vkusnenko/93899928770/
- Меню: https://yandex.ru/maps/org/vkusnenko/93899928770/menu/
- Галерея: https://yandex.ru/maps/org/vkusnenko/93899928770/gallery/

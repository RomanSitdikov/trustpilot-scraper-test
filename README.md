# Trustpilot Scraper
## Установка

1. Поднимите контейнеры:
   ```bash
   sudo docker compose up -d --build

2. Инициализируйте базу данных:
    Подождите несколько секунд после поднятия контейнеров, чтобы MySQL успела полностью стартовать, прежде чем запускать инициализацию:
   ```bash
   sudo docker compose run --rm scraper --init-db
   
   
   ```

3. Запустите парсинг ссылок:
    Отредактируйте файл links.txt, добавив в него ссылки на отзывы компаний, которые нужно пропарсить, а затем запустите:
   ```bash
   sudo docker compose run --rm scraper --source=links.txt


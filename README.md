# mini-promo-code-system
Скрипт для мини системы промокодов.

В данном скрипте уже заложена база: созадние/редактирвоание промокодов, логи (фильтрация логов активации) промокодов.
Ну и конечно сама активация промокода, вам лишь нужно будет дописать систему выдачи награды с промокода к вам на сервис, хотя можно и без этого, просто писать админу у которого есть доступ к админ панели, он проверит по нику пользовтеля в тг, правда ли миенно тв его активировал и выдаст тебе приз.


# Рекомендации
PHP-8.1+
MySQL-8.0+

# некоторые пояснения
Для смены пароля в админ панели зайдите в файл admin.php и в строке 17 измените его. <br>
Содержание строки: ``` $admin_password = 'admin123'; ```


# Автор / доработал
Maroz Studios / BYxarek

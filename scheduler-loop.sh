#!/bin/bash
while true
do
    /usr/bin/php /var/www/html/redis-laravel/artisan schedule:run
    sleep 60
done

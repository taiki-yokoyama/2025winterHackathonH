#!/bin/sh

cd /work/web
php artisan migrate
php artisan octane:start --host=0.0.0.0

#!/bin/bash
service nginx start
service php7.4-fpm start

exec "$@"

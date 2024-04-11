FROM php:8.2-cli-alpine3.19

COPY --chmod=777 ./mailgun.php /mailgun
CMD [ "/usr/local/bin/php", "/mailgun" ]

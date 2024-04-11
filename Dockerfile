FROM php:8.2-cli-alpine3.19

LABEL org.opencontainers.image.source https://github.com/liquidlight/mailgun-delivery-check
LABEL org.opencontainers.image.description "Mailgun Email Delivery Check"
LABEL org.opencontainers.image.licenses ISC

COPY --chmod=777 ./mailgun.php /mailgun
CMD [ "/usr/local/bin/php", "/mailgun" ]

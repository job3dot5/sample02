FROM nginx:1.27-alpine

# Symfony API virtual host
COPY default.conf /etc/nginx/conf.d/default.conf

# Local TLS certificates for development
COPY ssl /etc/nginx/ssl

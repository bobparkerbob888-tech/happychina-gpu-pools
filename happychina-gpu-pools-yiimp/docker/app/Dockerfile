FROM nginx:1.27-alpine

RUN rm -f /etc/nginx/conf.d/default.conf

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY entrypoint.sh /entrypoint.sh
COPY overlay/ /opt/happychina-yiimp/overlay/
COPY overlay/frontend-rustpool.html /usr/share/nginx/html/frontend-rustpool.html

RUN chmod +x /entrypoint.sh && \
    mkdir -p /app-data /opt/happychina-yiimp/overlay

ENTRYPOINT ["/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]

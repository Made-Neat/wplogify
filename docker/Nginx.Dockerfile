FROM nginx:1.22.1

# Copy the 404.html file into the container.
COPY 404.html /usr/share/nginx/html/404.html

# Copy the nginx.conf file into the container.
COPY nginx.conf /etc/nginx/nginx.conf

# How to set up the local containers for WP Logify development.


## Prerequisites
- Docker
- Docker Compose

## Steps

1. **Update Hosts File**

   Add this line to your `/etc/hosts` file to map `wplogify.localhost` to `127.0.0.1`:

```
127.0.0.1   wplogify.localhost
```
2. **Update Docker Compose File**

Update the path to the code in `docker-compose.yml` to suit your local setup. Specifically, update the first part of the second volume specification under `services > wordpress > volumes`.

```yaml
services:
  wordpress:
    volumes:
      - wordpress_data:/var/www/html
      - /path/to/your/local/code:/var/www/html      
```

3. **Build the Containers**

   Build the Docker containers:

```
docker-compose build
```

4. **Start the Containers**

   Start the Docker containers:

```
docker-compose -p madeneat-wplogify up -d
```

5. **Install WordPress**

   Open your browser and go to http://wplogify.localhost to complete the WordPress installation.


6. **Copy Plugin Code**

   Copy your plugin code to the wp-content/plugins/wp-logify directory:

```
cp -r /path/to/your/plugin wp-content/plugins/wp-logify
```

   Replace /path/to/your/plugin with the actual path to your plugin code.

## Notes

Ensure that Docker is running before starting the setup.

Adjust any other configuration settings in docker-compose.yml as needed for your environment.

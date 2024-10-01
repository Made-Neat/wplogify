# How to set up the local containers for Logify WP development.

## Prerequisites

- Docker
- Docker Compose

Ensure Docker is running before starting the setup.

Adjust any other configuration settings in docker-compose.yml as needed for your environment.

## Steps

1. **Update Hosts File**

Add this line to your `/etc/hosts` file to map `logifywp.localhost` to `127.0.0.1`:

```
127.0.0.1   logifywp.localhost
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

```
docker-compose build
```

4. **Start the Containers**

```
docker-compose up -d
```

5. **Install WordPress**

Open your browser and go to http://logifywp.localhost to complete the WordPress installation.

6. **Copy Plugin Code**

Copy your plugin code to the wp-content/plugins/logify-wp directory:

```
cp -r /path/to/your/plugin wp-content/plugins/logify-wp
```

Replace /path/to/your/plugin with the actual path to your plugin code.

# How to access the database from a SQL client

Driver: `MySQL`

Connect by: `Host`

Server host: `localhost` (or `127.0.0.1`)

Port: `3307`
As specified in docker-compose.yml, docker will port forward from `3307` on local to `3306` on the container.

**Make sure these match the settings in docker-compose.yml**

Database: `wplogifydev`

Username: `root`

Password: `freedom`

## How to backup the database

Make a backup folder. From that folder, run the following command. Be sure to update the path to mysqldump and the timestamp in the output filename. If you didn't change the passwords in `docker-compose.yml` then the password should be `freedom`.

```
/path/to/mysqldump --skip-lock-tables --routines --add-drop-table --disable-keys --extended-insert -u root --host=127.0.0.1 --port=3307 wplogifydev -p > database-backup-wplogifydev-<YYYY-MM-DD-HH-MM>.sql
```

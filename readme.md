DutyManager Web Page
=============

Installing
----------

Clone repository to web server's document path and run composer update
```
cd /var/www/
git clone https://github.com/tulinkry/dutymanager
cd dutymanager
composer update

# log and temp must be writable by the web server
sudo chown -R www-data:www-data {log,temp}
```


Create local configuration
```
cd /var/www/dutymanager/app/config
nano config.local.neon
```
Leave empty

```txt
parameters:

nette:

services:

factories:

```
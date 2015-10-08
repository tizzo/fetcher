# Setup #

## Ubuntu and CentOS
Fetcher runs on Ubuntu and CentOS. Patches welcome to add support for other operating systems.

At [Zivtech](https://zivtech.com) (home of Drush Fetcher) we develop on an [Ubuntu VM](https://github.com/zivtech/vagrant-development-vm) which already has Fetcher installed. Feel free to use that too!

Install Fetcher using drush

```
drush dl fetcher
```

## Folder and Service Permissions ##
Fetcher does some useful things such as setting up Apache virtual hosts for each site. To do this it must have write access to the directory where Apache stores these files. On Ubuntu systems this means that fetcher requires write access to "/etc/apache2/sites-available". On CentOs or similar systems this means that Fetcher needs write access to "/etc/httpd/conf.d"

To ensure your site is fully enabled Fetcher will also restart or reload Apache. As a result Fetcher needs access to these commands. You can run Fetcher as root but this is not necessarily the best idea. Better is to allow your user access to the necessary services.


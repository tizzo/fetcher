# Setup #

## Ubuntu ##
Fetcher was designed and tested using Ubnuntu servers and this is currently the most stable environment to run it on.
If all goes well, fetcher should just work on an Ubuntu server using Apache and MySql with standard setup.

## CentOs ##
Posix systems require the php-posix library to be added. Fetcher will fail without this.
Run "yum install php-posix" to resolve this issue.

## Folder and Service Permissions ##
Fetcher does some useful things such as setting up Apache virtual hosts for each site. To do this it must have write access to the directory where Apache stores these files. On Ubuntu systems this means that fetcher requires write acces
s to "/etc/apache2/sites-available". On CentOs or similar systems this means that Fetcher needs write access to "/etc/httpd/conf.d"

To ensure your site is fully enabled Fetcher will also restart or reload Apache. As a result Fetcher needs access to these commands. You can run Fetcher as root but this is not necessarily the best idea. Better is to allow your user access to the necessary services.


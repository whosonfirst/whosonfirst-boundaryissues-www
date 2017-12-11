#!/bin/sh

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`

UBUNTU=`dirname $WHOAMI`
ROOT=`dirname $UBUNTU`

sudo apt-get update
sudo apt-get -y upgrade

sudo apt-get -y install apache2 apache2-utils mysql-server
sudo apt-get -y install libapache2-mod-php php-mysql php-cli php-curl php-mcrypt php-mbstring libphp-predis

for mod in proxy.load proxy.conf proxy_http.load proxy_wstunnel.load proxy_fcgi.load rewrite.load ssl.conf ssl.load socache_shmcb.load headers.load
do

    if [ -L /etc/apache2/mods-enabled/${mod} ]
    then
        sudo rm /etc/apache2/mods-enabled/${mod}
    fi

    if [ -f /etc/apache2/mods-enabled/${mod} ]
    then
        sudo mv /etc/apache2/mods-enabled/${mod} /etc/apache2/mods-enabled/${mod}.bak
    fi

    sudo ln -s /etc/apache2/mods-available/${mod} /etc/apache2/mods-enabled/${mod}
done

for conf_disable in javascript-common
do
    if [ -L /etc/apache2/conf-enabled/${conf_disable} ]
    then
        sudo rm /etc/apache2/conf-enabled/${conf_disable}
    fi

    if [ -f /etc/apache2/conf-enabled/${conf_disable} ]
    then
        sudo mv /etc/apache2/conf-enabled/${conf_disable} /etc/apache2/conf-enabled/${conf_disable}.disabled
    fi
done

for ctx in apache2 cli
do

    for mod in mcrypt.ini
    do

        if [ -L /etc/php/7.0/${ctx}/conf.d/20-${mod} ]
        then
            sudo rm /etc/php/7.0/${ctx}/conf.d/20-${mod}
        fi

        if [ -f /etc/php/7.0/${ctx}/conf.d/20-${mod} ]
        then
            sudo mv /etc/php/7.0/${ctx}/conf.d/20-${mod} /etc/php/7.0/${ctx}/conf.d/20-${mod}.bak
        fi

        sudo ln -s /etc/php/7.0/mods-available/${mod} /etc/php/7.0/${ctx}/conf.d/20-${mod}
    done

    sudo perl -p -i -e "s/short_open_tag = Off/short_open_tag = On/" /etc/php/7.0/${ctx}/php.ini;
done

if [ ! -d ${ROOT}/www/templates_c ]
then
    mkdir ${ROOT}/www/templates_c
fi

sudo chgrp -R www-data ${ROOT}/www/templates_c
sudo chmod -R g+ws ${ROOT}/www/templates_c

sudo /etc/init.d/apache2 restart

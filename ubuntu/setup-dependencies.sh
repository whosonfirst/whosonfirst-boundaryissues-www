#!/bin/sh

sudo apt-get update
sudo apt-get upgrade -y
sudo apt-get install -y git tcsh emacs24-nox htop sysstat ufw fail2ban unattended-upgrades python-setuptools unzip
sudo apt-get install -y gdal-bin
sudo apt-get install -y golang
sudo apt-get install -y make nginx gunicorn python-gevent python-flask
sudo apt-get install -y postgresql-9.3 postgresql-client postgis postgresql-9.3-postgis-scripts python-psycopg2
sudo apt-get install -y ruby-ronn

# https://www.elastic.co/guide/en/elasticsearch/reference/current/setup-service.html

sudo add-apt-repository ppa:webupd8team/java -y
sudo apt-get install oracle-java8-installer -y

# https://www.elastic.co/guide/en/elasticsearch/reference/current/setup-repositories.html

wget -qO - https://packages.elastic.co/GPG-KEY-elasticsearch | sudo apt-key add -
echo "deb http://packages.elastic.co/elasticsearch/1.7/debian stable main" | sudo tee -a /etc/apt/sources.list.d/elasticsearch-1.7.list
sudo apt-get update && sudo apt-get install elasticsearch
sudo update-rc.d elasticsearch defaults 95 10

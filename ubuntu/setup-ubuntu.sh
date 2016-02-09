#!/bin/sh

# Testing
ls -la /usr/local/mapzen/whosonfirst-www-boundaryissues

sudo apt-get update
sudo apt-get upgrade -y

sudo apt-get install -y git tcsh emacs24-nox htop sysstat ufw fail2ban unattended-upgrades python-setuptools unzip
sudo apt-get install -y gdal-bin
sudo apt-get install -y golang
sudo apt-get install -y make gunicorn python-gevent python-flask

# https://www.elastic.co/guide/en/elasticsearch/reference/current/setup-service.html

sudo add-apt-repository ppa:webupd8team/java -y
sudo apt-get update
sudo apt-get install oracle-java8-installer -y

# https://www.elastic.co/guide/en/elasticsearch/reference/current/setup-repositories.html

wget -qO - https://packages.elastic.co/GPG-KEY-elasticsearch | sudo apt-key add -
echo "deb http://packages.elastic.co/elasticsearch/1.7/debian stable main" | sudo tee -a /etc/apt/sources.list.d/elasticsearch-1.7.list
sudo apt-get update && sudo apt-get install elasticsearch
sudo update-rc.d elasticsearch defaults 95 10

# make sure elasticsearch is running

if [ -f /var/run/elasticsearch/elasticsearch.pid ]
then
     sudo /etc/init.d/elasticsearch start
     sleep 10
else

	# make sure elasticsearch is actually running...
	PID=`cat /var/run/elasticsearch/elasticsearch.pid`
	# ps -p ${PID}
fi

if [ ! -d /usr/local/mapzen ]
then
    sudo mkdir /usr/local/mapzen
fi

sudo chown vagrant /usr/local/mapzen

# index all the data - this takes a while
# /usr/local/bin/wof-es-index -s /usr/local/mapzen/whosonfirst-data/data -b -v

# Setting up things from github:whosonfirst - see what's going on? basically configuring
# vagrant to do the right thing with ssh keys and stuff like github during the
# provisioning phase is a gigantic nuisance. So, we're just going to fake it for
# now and assume that it is possible to do all the usual GH stuff once you've
# logged in... (20151008/thisisaaronland)

if [ ! -d /usr/local/mapzen/py-mapzen-whosonfirst ]
then

	git clone https://github.com/whosonfirst/py-mapzen-whosonfirst.git /usr/local/mapzen/py-mapzen-whosonfirst
	sudo chown -R vagrant.vagrant /usr/local/mapzen/py-mapzen-whosonfirst

	cd /usr/local/mapzen/py-mapzen-whosonfirst
	git remote rm origin
	git remote add origin git@github.com:whosonfirst/py-mapzen-whosonfirst.git

	sudo python ./setup.py install
	cd -
else
	cd /usr/local/mapzen/py-mapzen-whosonfirst
	sudo python ./setup.py install
	cd -
fi

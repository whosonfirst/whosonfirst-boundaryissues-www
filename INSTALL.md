# Installing Boundary Issues

Hello friends, this is @dphiffer from Mapzen. Along with @thisisaaronland, I am helping to build this geodata editor you find before you.

We're building it out on [Flamework](https://github.com/whosonfirst/flamework), the web application framework created for Flickr (see also: [Flamework Design Philosophy](https://github.com/exflickr/flamework/blob/master/docs/philosophy.md)). Flamework has a lot of great stuff baked in. Stuff like APIs, authentication—it's working code, but doesn't have a huge range of new users trying it out. Some of the things that make (for example) WordPress easy to get up and running haven't been fleshed out yet.

I'm not sure how much easier we can make this to install. But as a first step, this document will serve to remind me of what the painful bits were along the way, so that I can remember for the next time I'm installing it. Or maybe it will be helpful to you, reader.

Before we begin: you're going to need some basic developer tools. If you haven't already, install Xcode (if you're on a Mac), or `sudo apt-get install build-essential git` if you're on Ubuntu Linux. At the very least you'll need `make` and `git` and [Vagrant](https://www.vagrantup.com/) (which uses [VirtualBox](https://www.virtualbox.org/), install that too).

## Get the code

Let's clone some repos, shall we?

```
mkdir -p /usr/local/mapzen
cd /usr/local/mapzen
git clone git@github.com:whosonfirst/whosonfirst-www-boundaryissues.git
git clone git@github.com:whosonfirst/vagrant-whosonfirst-www-boundaryissues.git
```

## Get the data

This next one is going to take some time and disk space (22 GB presently). Prepare yourself.

```
git@github.com:whosonfirst/whosonfirst-data.git
```

Just a note here to mention these repository URLs are all in the SSH flavor, since I intend to commit back to them at some point. You may find the HTTPS ones more to your liking. It's up to you, just thought I'd mention it.

Onward.

```
cd /usr/local/mapzen/whosonfirst-data
make setup
```

If you're using [oh-my-zsh](https://github.com/robbyrussell/oh-my-zsh) your git integration will freak out for a moment, but it'll get fixed by the `make setup` command. Another thing's worth mentioning is we intend to make this data downloading process go a lot faster but limiting how much stuff gets pulled down. ([see also](https://github.com/whosonfirst/whosonfirst-www-spelunker/tree/data#data-sources))

## Bring up the Virtual Machine

Time to create our VM!

```
cd /usr/local/mapzen/vagrant-whosonfirst-www-boundaryissues
vagrant up
```

A lot of things will happen, and you will see lots of green and red text.

When it finishes, login via SSH:

```
vagrant ssh
```

## Finish installing

Oh hey, we logged into the new VM! Exciting. This is an Ubuntu box, so this next part should not be surprising:

```
sudo apt-get update
sudo apt-get upgrade
```

You're probably going to see an error about MySQL not being installed completely. That's because we need to set the MySQL root password. At some point we should fix it so the password gets set in the provisioning step.

For now, let's take the advice of the error message:

```
sudo apt-get -f install
```

Type a root MySQL password into the ncurses-style prompt. And after that your stuff should finish installing.

Do this again, just to be sure:

```
sudo apt-get upgrade
```

## Ubuntu setup stuff

Let's go to the shared directory, but on the Ubuntu VM side of things.

```
cd /usr/local/mapzen/whosonfirst-www-boundaryissues/
```

Same folder as the one on your host machine. If you do a `df` command, you will see that `whosonfirst-data` is also mounted as a shared folder.

Run the Ubuntu setup script:

```
./ubuntu/setup-ubuntu.sh .
```

At one point you'll be asked by another ncurses-style UI to accept the legal terms of Oracle Java™. And a whole bunch of other stuff will happen.

## Configure Apache

Let's disable the default configuration before we continue.

```
sudo a2dissite 000-default
```

Let's create a new one and symlink it in.

```
cd /usr/local/mapzen/whosonfirst-www-boundaryissues/apache/
cp whosonfirst-www-boundaryissues.conf.example whosonfirst-www-boundaryissues.conf
cd /etc/apache2/sites-enabled
sudo ln -s /usr/local/mapzen/whosonfirst-www-boundaryissues/apache/whosonfirst-www-boundaryissues.conf
```

Now you should edit the config file. Here's what mine looks like. I had to do a thing, switching the `Allow` to a `Require` configuration, [to make Apache 2.4 happy](https://httpd.apache.org/docs/2.4/upgrading.html#access).

```
<VirtualHost *:80>

	DocumentRoot /usr/local/mapzen/whosonfirst-www-boundaryissues/www
	
	<Directory />
		Options FollowSymLinks
		AllowOverride None
	</Directory>

	<Directory /usr/local/mapzen/whosonfirst-www-boundaryissues/www>
		Options FollowSymLinks Indexes
		AllowOverride All
		Require all granted
	</Directory>

	ErrorLog ${APACHE_LOG_DIR}/error.log
	LogLevel warn
	CustomLog ${APACHE_LOG_DIR}/access.log combined
	
</VirtualHost>
```

Now let's try restarting Apache, and see if things are working.

```
sudo apachectl restart
```

And try loading up http://localhost:8989/

I got a 500 error. So I checked the error log: `sudo tail /var/log/apache2/error.log`. Gotta enable mod_rewrite. Maybe something we can add to the provisioning script?

```
sudo a2enmod rewrite
sudo apachectl restart
```

After reloading, I see that Flamework isn't finding the mcrypt stuff it likes to have around. Let's fix that.

```
cd /etc/php5
sudo ln -s mods-available/mcrypt.ini apache2/conf.d/mcrypt.ini
sudo php5enmod mcrypt
sudo apachectl restart
```

Now if I reload the page I get a Flamework-rendered error message, so we're making progress!

I need to attend to `www/include/secrets.php`. I tried generating it from `bin/configure_secrets.php`, but that script seems to leave out the `$GLOBALS` part of the configurations (there's a comment that mentions something along these lines, worth revisiting).

Basically edit `/usr/local/mapzen/whosonfirst-www-boundaryissues/www/include/secrets.php` so that it looks like this:

```
<?php

	$GLOBALS['cfg']['crypto_cookie_secret'] = '';
	$GLOBALS['cfg']['crypto_password_secret'] = '';
	$GLOBALS['cfg']['crypto_crumb_secret'] = '';

	$GLOBALS['cfg']['db_main']['pass'] = '';
	$GLOBALS['cfg']['db_users']['pass'] = '';
	$GLOBALS['cfg']['db_poormans_slaves_pass'] = '';

	# the end
```

## Database setup

Now we need to set the actual database up, logging in with the MySQL root user, with the password we set earlier.

```
mysql -u root -p
```

Create the database, and the account that will login to it:

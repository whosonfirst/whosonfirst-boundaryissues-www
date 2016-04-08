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

Before you clone the data repository, you'll need to install [git-lfs](https://git-lfs.github.com/). On my machine, this was just a matter of typing `brew install git-lfs`.

This next repo is going to take some time and disk space (22 GB presently). Prepare yourself.

```
git clone git@github.com:whosonfirst/whosonfirst-data.git
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

Create the database, and the account that will login to it (make up a good password here):

```
CREATE DATABASE boundaryissues;
CREATE USER 'boundaryissues'@'localhost' IDENTIFIED BY '...';
GRANT ALL ON boundaryissues.* TO 'boundaryissues'@'localhost';
```

## Day two

So we made some progress, but things are still not quite fully installed yet. Between the time that I wrote up the preceding, I committed it to GitHub and then came back to work the next day, @thisisaaronland wrote me an email pointing to some helpful setup scripts (that I am now adding to this repository):

* [setup.sh](https://github.com/whosonfirst/flamework-tools/blob/master/ubuntu/setup.sh)
* [setup-apache.sh](https://github.com/whosonfirst/flamework-tools/blob/master/ubuntu/setup-apache.sh)
* [setup-db.sh](https://github.com/whosonfirst/flamework-tools/blob/master/ubuntu/setup-db.sh)

So I'm going to start over with a fresh VM, reduce the number of things the Vagrantfile does during its provision stage, and see how far those scripts can get me. A couple things worth noting before I move along:

The order you execute those setup scripts matters. Without the `short_open_tag` configuration from setup.sh, you can't expect the PHP scripts to run in setup-db.sh. I think we should rename `ubuntu/setup.sh` to something more descriptive and create a top-level `setup.sh` that calls the individual steps in the `ubuntu` folder. (A solution to that particular `short_open_tag` problem is to just *remove all the open short tags*, which seems like a good choice for being compatible with PHP default settings.)

Also, I've imposed some weirdness with how I'm doing Vagrant folder syncing, relating to file permissions. There's a conflict between wanting to be able to execute shell scripts in the `ubuntu` folder and being able to write to `templates_c` by the `www-data` user. Due to how Vagrant handles folder sync mount points, the `whosonfirst-www-boundaryissues` ownership is either owned by the `vagrant` user (and allows for executable shell scripts) *or* it's owned by `www-data` (and allows for writing to the `templates_c` folder).

At the core of this issue is that I use a native OS X editor instead of emacs or vim. This means folder syncing is essential for me doing development in a familiar Mac GUI, and also having my files saved to the OS X file system getting sync'd up with the ones in my Ubuntu VM. If you're not doing development, it's kind of a non-issue, so maybe we should default to settings that work for *users* of the VM and be aware that the Vagrantfile needs to be adjusted if you plan on tweaking the code.

For now the workaround is to boot with a `vagrant` style mount point during installation, then change the Vagrantfile to `www-data` mount point for ongoing development. It's not ideal, but at least I think I've identified the core issues.

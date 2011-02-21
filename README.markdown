# CampFireManager #

CampFireManager is designed to help you organise talks at BarCamp and
Unconference Style events. By automatically sorting rooms by number of
attendees, dynamically finding time slots when your selected time slot is full
and making full use of external sources to help streamline attendees
interaction, CampFireManager is a simple-to-use system to make the most of
your event.

## License ##

All code, unless otherwise noted, is released under :

    GNU Affero General Public License, version 3.0

Author: Jon Spriggs (jon@spriggs.org.uk) 

Date: 2010-01-28

Version: 0.1-ALPHA

## Requirements ##

These packages are based on requirements in Ubuntu:

    gammu
    gammu-smsd
    apache2
    php5-mysql
    mysql-server
    php5-cli
    php5-gmp (for the OpenID packages)

## Optional Extras  ##

    PageKite (see below for details)

## Installation ##

Install the above packages, then create the MySQL Users and Tables for both 
Gammu (the SMS engine) and CampFireManager. There's no technical reason why
these can't both be within the same database space, but for clarity, I have
separated the presentation database from the command databases. Gammu's 
database structure (on Ubuntu at least) is in 
<tt>/usr/share/doc/gammu/examples/sql/mysql.sql.gz</tt>

Firstly, create the users and the databases - substitute username, password
and hostname with appropriate values. Perform these steps for both Gammu and
CampFireManager.

<pre>
echo "CREATE USER 'username'@'localhost' IDENTIFIED BY 'password';
GRANT USAGE ON *.* TO 'username'@'localhost' IDENTIFIED BY 'password' WITH 
  MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 
  MAX_USER_CONNECTIONS 0;
CREATE DATABASE IF NOT EXISTS databasename;
GRANT ALL PRIVILEGES ON databasename.* TO 'username'@'localhost';" | mysql -u root -p
</pre>

Next, import the table configuration for Gammu

    gunzip -c /usr/share/doc/gammu/examples/sql/mysql.sql.gz | mysql -u username -p databasename

Then the table configuration for CampFireManager

    mysql -u username -p databasename < sql

For each phone or phone-dongle you are connecting, you need to create a text
file, containing the following data:

<pre>
[gammu]
port = <path_to_usb_serial>
Connection = at19200

[smsd]
PhoneID = <phone_name>
CommTimeout = 5
DeliveryReport = sms

service = mysql
user = <database_username>
password = <database_password>
pc = <database_host>
database = <database_data_store>

LogFormat = textall
logfile = stdout
debuglevel = 1
</pre>

For each of those text files, run the following command (you must edit
<tt>/path/to/config</tt>, to match what you have):

    sudo gammu-smsd -c /path/to/config -U gammu

This will confirm that your configuration file is correct for your phone
device. Next, run

    sudo /path/to/CampFireManager/run_svc.sh gammu-smsd -c /path/to/config -U gammu

This will start the script running, even if the script self-terminates.

Configure the CampFireManager database settings by editing the file
/path/to/CampFireManager/db.php and adding the appropriate database settings.

Lastly, run

    /path/to/CampFireManager/run_svc.sh php -q /path/to/CampFireManager/daemon.php

Again, this will keep the script running.

If your instance of CampFireManager is at http://localhost/ then your
administration interface is at http://localhost/admin.php - it will force you 
to log in using OpenID first. Once you've authenticated, you'll have to click
on the "Modify config values" link on the main page.

## Optional Extra -- PageKite ##

### Intro ###

If you are running CampFireManager at an event where you have multiple attendees
with smartphones or 3G access, it may be useful to provide external access to
the CampFireManager instance without hosting a second instance of the service.

In this context, PageKite might be useful, if you do not have access to the NAT
or port forwarding configuration on the site's router/firewall. PageKite is
designed to provide a link between a locally hosted webserver and a public facing
server, without requiring extensive configuration or access to routers.

### Installing PageKite ###

Follow the installation guide at http://pagekite.net/wiki/Howto/GNULinux/DebianPackage/

### Choosing your end-point ###

PageKite can be run with a hosted or self-hosted "front-end" (AKA the public
facing bit). Both are equally easy to configure on the "back-end" (AKA the 
local end) and configuring a self-hosted "front-end" is *relatively* simple to
achieve.

Personally, for a smaller event (<100 attendees), I would use the PageKite.me 
service from pagekite.net which provides up to 5Gb of data use, while with a
larger event, host your own endpoint, or put some funds towards PageKite.net
and enable a great opensource project to grow!

If you want to use PageKite.me, go to http://PageKite.net and register an 
account. Download the settings file for Linux and then from a command line, run
the following command:
  grep "backend=" pagekite.rc | sudo tee -a /etc/pagekite/local.rc

then start the pagekite service by running:
  sudo /etc/init.d/pagekit start

If you want to self-host, see the next section for setting up your pagekite
front-end, and the settings you'll need to pair the two ends up.

### Setting up your own PageKite front-end ###
Install the pagekite package on your front-end machine, then add the following
lines to your /etc/pagekite/pagekite.rc file:

  # Run your own PageKite front-end
  isfrontend
  host=HOST_INTERFACE_IP_ADDRESS
  ports=80,443
  protos=http,https
  domain=http,https:EXTERNAL_WEB.SVC.ADDR.ESS:A_C0MPl£x_Pa55w0rd
  # Or, slightly more securely, than the above line, specify
  # per-host entries on each line. Use * as a wildcard.
  #
  # Format of these are: domain=service1,service2/port:HOSTNAME:Password
  #
  #domain=http,https:www.my-web-host:A-Password
  #domain=http,https:*.public-web-svc:Another-Password

  # If you've got an existing web service running on this box
  # You'll either need to stop it for the duration of the event
  # or, change the IP address it listens on to something in the
  # 127.0.0.0/8 subnet - e.g. 127.1.1.1 or (more usually) 127.0.0.1
  # Because you're hiding your local services behind PageKite, you
  # don't need to include a password. You can also include any other
  # backend addresses in this too without supplying a password, it's
  # only externally connecting services that need the above password.
  # Delete these, or comment them out, if this web host never normally
  # provides a webservice.
  #
  # Format of these are: backend=service:HOSTNAME:LocalAddress:Port:Password

  backend=https:YOUR.EXTERNALLY.RESOLVABLE.NAME:127.0.0.1:443:
  backend=http:YOUR.EXTERNALLY.RESOLVABLE.NAME:127.0.0.1:80:
  backend=https:YOUR.IP.ADDR.ESS:127.0.0.1:443:
  backend=http:YOUR.IP.ADDR.ESS:127.0.0.1:80:
  backend=https:YOUR-INTERNAL-HOSTNAME:127.0.0.1:443:
  backend=http:YOUR-INTERNAL-HOSTNAME:127.0.0.1:80:

  # Comment out this section, as you don't need it if you're running
  # your own instance!
  #
  # Use the pageKite.net service by default
  #frontends=1:frontends.b5p.us:443
  #dyndns=pagekite.net

Once you've got all this lot sorted out, next, on your backend machine, edit
/etc/pagekite/pagekite.rc and change the following:

  # You don't want to be using the pagekite.net service
  # so therefore, comment out the following block!
  #
  # Use the pageKite.net service by default
  #frontends=1:frontends.b5p.us:443
  #dyndns=pagekite.net

  # Because I've not yet figured out how to do the TLS encryption - use
  # cleartext in the tunnel right now. Any HTTPS connections would be
  # encrypted up to the webserver *anyway*, and at least this way it works!
  # If someone wants to submit a patch to the documentation for this project
  # - Either CampFireManager or PageKite, that'd be rather useful :)
  #
  frontend=FRONTEND_HOSTNAME:80

  # As we're not using TLS yet, comment out this line, as it won't work
  # for this connection.
  # I'm guessing this is where my issue is with the previous comment, but
  # as I'm not sure, please, if you've got any suggestions, let me know!
  #
  # Enable TLS encryption for your tunnel, comment out for a plaintext tunnel
  #fe_certname=frontends.b5p.us

Next, edit /etc/pagekite/local.rc. At the very end of the file, ensure all the
existing backends are commented out, and then add your own

  backend=http:EXTERNAL_WEB.SVC.ADDR.ESS:localhost:80:A_C0MPl£x_Pa55w0rd
  backend=https:EXTERNAL_WEB.SVC.ADDR.ESS:localhost:443:A_C0MPl£x_Pa55w0rd

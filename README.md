authsaml plugin for dokuwiki
============================

This plugin is a mix of the [ssp](https://www.dokuwiki.org/auth:ssp) and the [simplesamldokuwiki](http://code.google.com/p/simplesamldokuwiki/>) plugin


This plugin allow you to different workflows:

1. SAML ONLY

    Allow only the saml authentication backend. Then the users will be stored in a plaintext file ``data/users.saml.php``

    When the user access to the login page, he will be redirected to the IdP. After authenticate:
 
        a) If is the first time, the user account will be created (stored in the file)

        a) If the account already exists, the user account will be updated (stored in the file)


    HOW get that mode?

    Easy, set in the conf folder of dokuwiki the:

        $conf['authtype'] = 'authsaml';


2. INTERNAL AUTH BACKEND + SAML

    Enable SAML as an extra authentication backend. You can choose if store the user data in the internal auth backend or in a 
    extra file (data/users.saml.php)

    You can let that the user log in dokuwiki using the normal login form or avoid that by forcing the redirection

    HOW get that?

    Easy, take a look on the configuration params of the authsaml plugin. [review the configuration section of that document]


Doc index:
 * How install and configure simpleSAMLphp as SP
 * How install and enable the authsaml plugin


How install and configure simpleSAMLphp as SP
=============================================

Install simpleSAMLphp
---------------------

First of all install the [simpleSAMLphp library dependences](http://simplesamlphp.org/docs/stable/simplesamlphp-install#section_3):

    CentOS --> yum install php5 php-ldap php-mbstring php-xml mod_ssl openssl
    Debian --> apt-get install php5 php5-mcrypt php5-mhash php5-mysql openssl


Now we can download the [latest version of simpleSAMLphp](http://code.google.com/p/simplesamlphp/downloads/list>), now is the 1.11: ::

 Directly --> http://simplesamlphp.googlecode.com/files/simplesamlphp-1.11.0.tar.gz
 From subversion repository --> svn co http://simplesamlphp.googlecode.com/svn/tags/simplesamlphp-1.11.0

Put the simplesamlphp directory at ``/var/www/simplesamlphp``


The SSL cert
------------

simpleSAMLphp requires a SSL cert. We can buy one or create it.

Create a self-signed cert
.........................

In order to generate a self-signed cert you need openssl:

    Centos --> yum install openssl
    Debian --> apt-get install openssl

Using OpenSSL we will generate a self-signed certificate in 3 steps.

* Generate private key:

    openssl genrsa -out server.pem 1024

* Generate CSR: (In the "Common Name" set the domain of your instance)

    openssl req -new -key server.pem -out server.csr

* Generate Self Signed Key:

    openssl x509 -req -days 365 -in server.csr -signkey server.pem -out server.crt

Override the certs of the ``/var/www/simplesamlphp/cert`` folder with the  generated certs.


Configure simpleSAMLphp
-----------------------

Copy the default config file from the template directory:

    cp /var/www/simplesamlphp/config-templates/config.php /var/www/simplesamlphp/config/config.php


And configure some values:

    'auth.adminpassword' => 'secret'      # Set a new password for admin web interface

    'enable.saml20-idp' => true,          # Enable ssp as IdP

    'secretsalt' => 'secret',             # Set a Salt, in the config file there is documentation to generate it

    'technicalcontact_name' => 'Admin name',          # Set admin data
    'technicalcontact_email' => 'xxxx@example.com',

    'session.cookie.domain' => '.example.com',        # Set the global domain, to share cookie with the rest of componnets

In production environment set also those values:

    'admin.protectindexpage'        => true,    # To protect the index page of simpleSAMLphp
    'debug'                 =>      FALSE,
    'showerrors'            =>      FALSE,      # To hide error-trace


Change the permission for some directories, execute the following command at the simpleSAMLphp folder:

    chown -R apache:apache cert log data metadata



Copy the default authsource file from the template directory:

    cp /var/www/simplesamlphp/config-templates/authsources.php /var/www/simplesamlphp/config/authsources.php

And set admin and an sp source. Something like:

    <?php

        $config = array(

                'admin' => array(
                        'core:AdminPassword',
                ),

                'dokuwiki' => array(
                    'saml:SP',

                    'entityID' => 'https://sp.example.com/simplesaml/module.php/saml/sp/metadata.php/dokuwiki',
                    
                    'idp' => 'https://idp.example.com/simplesaml/saml2/idp/metadata.php', # Set the entityID of the IdP you gonna use
		
                    'privatekey' => 'server.pem',
                    'certificate' => 'server.crt',
                ),
        );
    ?>


And now paste the metadata of your IdP in simpleSAMLphp format at ``/var/www/simplesamlphp/metadata/saml20-idp-remote.php``


Apache configuration
--------------------

The apache configuration may look like this:

 <VirtualHost *:80>
        ServerName sp.example.com
        DocumentRoot /var/www/simplesamlphp/www

        Alias /simplesaml /var/simplesamlphp/www
 </VirtualHost>

 <VirtualHost *:443>
        ServerName sp.example.com
        DocumentRoot /var/www/simplesamlphp/www

        Alias /simplesaml /var/simplesamlphp/www

        SSLEngine on
        SSLCertificateFile /var/www/simplesamlphp/cert/server.crt
        SSLCertificateKeyFile /var/www/simplesamlphp/cert/server.pem
 </VirtualHost>



Make sure that your IdP server runs HTTPS (SSL)



NTP server
----------

To get Saml2 run correctly we need have sure that all machine's clock are synced.

Install ntp: 

    Centos --> yum install ntp
    Debian --> apt-get install ntp

Configure the ntp service `/etc/ntp.conf`:

    server 0.uk.pool.ntp.org
    server 1.uk.pool.ntp.org
    server 2.uk.pool.ntp.org
    server 3.uk.pool.ntp.org

`Check the` [ntp server list](http://www.pool.ntp.org/use.html) `and use the server that is near from your server.`

Enable the server and put it on the system boot

    Centos --> service ntpd start
               chkconfig ntpd on

    Debian -> /etc/init.d/ntp start
              update-rc.d ntp defaults



More info at [http://simplesamlphp.org/docs/stable/simplesamlphp-sp](http://simplesamlphp.org/docs/stable/simplesamlphp-sp)



How install and enable the authsaml plugin
==========================================

1. Configure the authsaml plugin editing conf/default.php . There are some parameters that must be configured:

    'simplesaml_path'. This refers to the path of the simpleSAMLphp folder. For example: /var/www/simplesamlphp

    'simplesaml_authsource'. Select the SP source you want to connect to moodle. (Sources are at the SP of simpleSAMLphp in config/authsources.php).

    'simplesaml_uid'. It is the SAML attribute that will be mapped to the Dokuwiki username. For example 'uid'.

    'simplesaml_mail'. It is the SAML attribute that will be mapped to the Dokuwiki mail. For example 'mail'. 

    'simplesaml_name'. It is the SAML attribute that will be mapped to the Dokuwiki cn. For example 'cn'.

    'simplesaml_grps'. It is the SAML attribute that will be mapped to the Dokuwiki groups. 
     // For example  'eduPersonAffiliation'.

    'use_internal_user_store' False mean that users will be stored in a separate file.
                               If the authtype is set to authsaml, then the users will be always stored in a separate file

    'force_saml_login'. When enabled, this will hide the normal login form and redirect directly to the IdP.
                         If the authtype is set to authsaml,  then the redirection will be always done


2. Copy the authsaml folder into dokuwiki plugin folder (lib/plugins)


3. Log in as admin, access to the Plugin Manager Panel and enable the authsaml plugin.


4. Set the authsaml as your authtype if you only want to allow this kind of authentication. Edit conf/local.php in the dokuwiki folder and add:

        $conf['authtype'] = 'authsaml';


    Otherways do nothing and the core auth plugin and the authsaml plugin will work together.



The "Session lost" problem
--------------------------


Note: If you experience a "session lost" when trying to log in the dokuwiki, you have some alternatives to solve the problem:

  1. Configure the simpleSAMLphp SP to handle the session using 'memcache' or 'sql' in order to avoid the session conflicts.
     	(Edit config/config.php):

		'store.type' => 'memcache',

		(ref: http://simplesamlphp.org/docs/stable/simplesamlphp-maintenance#section_2)


  2. If your simpleSAMLphp SP instance only has dokuwiki as service, you can change the name of the cookie and set the same 
     cookiename than dokuwiki (Edit config/config.php):

		'session.phpsession.cookiename'  => 'DokuWiki',


  3. If your simpleSAMLphp SP instance has several services is easier to change the cookiename at dokuwiki.
        (Edit inc/init.php):

		session_name("DokuWiki");      // line 144

 	 Set the same cookiename value that used in simplesamphp (config/config.php) 'session.phpsession.cookiename'


  Extra info about "session lost":

		https://code.google.com/p/simplesamlphp/wiki/LostState
		https://code.google.com/p/simplesamlphp/wiki/PHPSessions

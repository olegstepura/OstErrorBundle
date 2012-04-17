OstErrorBundle
==============

Render an error if IP is in allowed list or send an email on each error raised or
exception thrown in production mode of a deployed symfony application (you should
know that something went wrong).

Usage
-----

Install the bundle as usual. Enable the bundle in AppKernel for all modes:

 `app/AppKernel.php`

``` php
<?php
        $bundles = array(
            // ...
            new Ost\ErrorBundle\OstErrorBundle($this),
            // ...
        );
```

Be sure to check that mail sending works with your
current configuration. Now edit configuration files:

 `app/config/config.yml`

``` yaml
ost_error:
  display:
    ips: ['10.10.10.2', '10.10.10.3']
```

Here we entered the IP's from which you are allowed to see the warning notifications
on the site in production mode (in debug it's converted to exceptions by Symfony).

 `app/config/config_dev.yml`

``` yaml
ost_error:
  mailer: false
  display: true
```

Disabled the mailer in dev mode since you are actually not going to recieve mails
on errors while in dev mode.

 `app/config/config_prod.yml`
 
``` yaml
ost_error:
  mailer:
    to: your.email@provider.com
    from: server.email@server.com
    report_not_found: false
```

Just enter your mail here. And tick the option if you want to recieve notifications
on `NotFoundHttpException`.


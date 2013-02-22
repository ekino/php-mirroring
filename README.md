Packagist and Github mirroring
==============================

Experiencing Github issues ? You want a local cache to speed up tests and deployment ?


Mirroring Packagist
-------------------

1. Set up a new host on your webserver : packagist.mycompany.com

2. Go to the document root for the new host

3. run `git clone git://github.com/ekino/php-mirroring.git .`

4. run `cp config.php.sample config.php`

5. edit rules: `vim config.php`

6. run `php mirror.php` - this can take a while for the first one

7. add a new cron job `sudo crontab -u www-data -e`:

        0 */1 * * * cd /PATH_TO_DOCUMENT_ROOT && php mirror.php

Mirroring Github
----------------

1. Create a new mirror directory

        mkdir mirrors/github.com

2. Add new mirror

        cd mirrors/github.com
        git clone --mirror git://github.com/ekino/php-mirroring.git ekino/php-mirroring.git

    Of course you need to repeat this operation for each mirror.

3. Setup a cron to update the mirror:

        0 */1 * * * /git/repositories/mirrors/update-mirrors.sh

4. Add the file : `vim /git/repositories/mirrors/update-mirrors.sh`

        #!/bin/sh
        cd /git/repositories/mirrors/github.com

        for i in */*.git; do
          cd $i

          url=`echo $i | sed "s/.git//"`

          echo "   > check 404 on https://github.com/${url}"
          curl -ILs https://github.com/${url} | head -n 1 | grep "HTTP/1.1 200 OK" > /dev/null

          if [ $? -eq 0 ] ; then
              echo "   > fetching git changes"
              git fetch
          fi

          #echo "end ..."
          cd ../..
        done


Usages
------

### Using source mode with gitolite

1. Setup a Gitolite

2. Add a new entry

        repo mirrors/..*
            R = @team

3. set the source mode to true

        function include_source() 
        {
            return true;
        }

### Using dist mode with a http server

1. setup a new php instance with a dedicated vhost

2. make sure the ``replace_dist_host`` function point to the correct vhost

        function replace_dist_host(array $metadata)
        {
            list($vendor, $name) = explode("/", $metadata['name']);

            if (!preg_match('@(https://api.github.com/repos/|https://github.com/|https://bitbucket.org)([a-zA-Z0-9_\-\.]*)/([a-zA-Z0-9_\-\.]*)/(zipball|archive|get)/([a-zA-Z0-9\.\-]*)(|.zip)@', $metadata['dist']['url'], $matches)) {
                return '';
            }

            $host = sprintf('http://packagist.mycompany.com/cache.php/github.com/%s/%s/%s.zip',
                $matches[2],
                $matches[3],
                $metadata['dist']['reference']
            );

            return $host;
        }

        function include_dist() 
        {
            return true;
        }

        function include_source() 
        {
            return true; // set to false if you only want to expose distribution
        }

### Composer.json


1. Make sure you have a clean project

        rm -rf composer.lock vendor

2. update the ``composer.json`` file to disable packagist and add the new one

        {
            "repositories":[
                { "packagist": false },
                { "type": "composer", "url": "http://packagist.mycompany.com"}
            ],
            
            // ...
        }

3. Install the dependencies

        php composer.phar install


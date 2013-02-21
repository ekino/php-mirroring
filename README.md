Packagist and Github mirroring
==============================

Experimenting Github issues ? You want a local cache to speed up tests and deployment ?


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

Mirroring Github with gitolite
-------------------------------

1. Setup a Gitolite

2. Add a new entry

        repo mirrors/..*
            R = @team

3. Create a new mirror directory

        mkdir mirrors/github.com

4. Add new mirror

        cd mirrors/github.com
        git clone --mirror git://github.com/ekino/php-mirroring.git ekino/php-mirroring.git

    Of course you need to repeat this operation for each mirror.

5. Setup a cron to update the mirror:

        0 */1 * * * /git/repositories/mirrors/update-mirrors.sh

6. Add the file : `vim /git/repositories/mirrors/update-mirrors.sh`

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

#!/bin/bash
#delete all repository temporary files older than a day old
/bin/find /var/www/html/azizi.ilri.org/repository/tmp/ ! -newermt "1 day ago" -exec rm -rf {} \;
#delete all repository download files older than a day old
#/bin/find /var/www/html/azizi.ilri.org/repository/download/ ! -newermt "1 day ago" -exec rm -rf {} \;
#delete all phpexcel temporary files in the DMP workflows older than a day old
/bin/find /var/www/html/azizi.ilri.org/odk_workflow/ -type d -iname "phpexcel_cache" ! -newermt "1 day ago" -exec rm -rf {} \;
#delete all temporary files in DMP workflows older than a day old
/bin/find /var/www/html/azizi.ilri.org/odk_workflow/ -type d -iname "tmp" ! -newermt "1 day ago" -exec rm -rf {} \;
#!/bin/bash
. /opt/psqr/utils/psqr-utils.sh

ITEM_COUNT=500

{
    # navigate to public square api project
    cd /var/www/psqr-api

    # update mainstream politics
    php bin/console feed:feedname politics-mainstream \
        /var/www/psqr-api/feed-json-templates/politics-mainstream.json $ITEM_COUNT
} 2>&1 | log

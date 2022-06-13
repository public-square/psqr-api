#!/bin/bash
. /opt/psqr/utils/psqr-utils.sh

ITEM_COUNT=500

{
    # navigate to public square api project
    cd /var/www/psqr-api

    # update right leaning politics
    php bin/console feed:feedname politics-right-leaning \
        /var/www/psqr-api/feed-json-templates/politics-right-leaning.json $ITEM_COUNT
} 2>&1 | log

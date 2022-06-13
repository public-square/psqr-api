#!/bin/bash
. /opt/psqr/utils/psqr-utils.sh

D=`date +%Y%m%d%H%M`
D1=`date -d "now 1 minutes ago" +%Y-%m-%d-%H-%M`
url="https://broadcast.staging.ology.com/broadcast/$D1.jsonl"

{
    echo "Running Ingestion for URL: $url"
    cd /var/www/psqr-api/src/Scripts
    /usr/local/bin/node ./psqr-content-ingest.js -u $url --feed --search
    echo "Finished running ingestion"
} 2>&1 | log

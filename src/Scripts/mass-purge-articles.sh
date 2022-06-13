#!/usr/bin/env bash

searchFor=$1
feedName=$2

echo "Searching for: ${searchFor} in feed called ${feedName}"

readarray -t hashes < <(curl --silent https://broadcast.staging.ology.com/feed/${feedName}/latest.jsonl | grep ${searchFor} | jq .metainfo.infoHash | xargs -l echo)

if [ -z "$hashes" ]; then
    echo "No Results Found for ${searchFor} in feed called ${feedName}"
else
    echo "Found ${#hashes[@]} Total InfoHash(es) in the feed"
    echo "Purging InfoHashes from ES Indices..."
    php bin/console es:purge-infohash ${hashes[*]}
fi

exit 1

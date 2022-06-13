#!/bin/bash

# set the limit of firehose latest files
length=500

# iterate through broadcast firehose latest files
for fh in /var/www/psqr-api/public/broadcast/latest.jsonl
do
  flock --exclusive --wait 5 $f \
    sh -c "tail -${length} ${fh} >${fh}.temp ; cat ${fh}.temp >${fh} ; rm ${fh}.temp"
done

#!/usr/bin/env bash

# set firehose directory
broadcast="/var/www/psqr-api/public/broadcast/"


# remove outdated time-sharded firehose files
function cleanse(){
  # regular expressions to match digits
  dd='[0-9][0-9]'
  dddd="${dd}${dd}"

  # get shard level as parameter
  shard=$1
  case $shard in

    minute)
      filename="${dddd}-${dd}-${dd}-${dd}-${dd}.jsonl"
      filter="-mmin +1"
      ;;

    hour)
      filename="${dddd}-${dd}-${dd}-${dd}.jsonl"
      filter="-mmin +60"
      ;;

    day)
      filename="${dddd}-${dd}-${dd}.jsonl"
      filter="-mmin +1440"
      ;;

    *)
      return 0
      ;;
  esac

  # -exec rm {} \;


  # minute files more than 1 minute old
  find $broadcast \
    -regextype posix-egrep \
    -name "$filename" \
    $filter -print0 | while read -d $'\0' parent
  do
    parent=${parent/.jsonl/}
    parent=${parent/$broadcast/}

    children=$(find $broadcast -regextype posix-egrep -name "${parent}-${dd}.jsonl")
    echo $parent
    echo $children
    echo
  done

}


echo "Beginning Clean Up"

cleanse "minute"
cleanse "hour"
cleanse "day"

echo "Finished Clean Up"

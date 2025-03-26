#!/bin/bash

DOMAIN="https://ipema.org"
URL="/about-ipema/?cron=$1"
CODE=0

while [ $CODE != "200" ]; do
    if [[ ${URL:0:4} != 'http' ]]; then
        URL="$DOMAIN$URL"
    fi
    wget -q --server-response --no-check-certificate --max-redirect=9999 -o html -O - "$URL" > /dev/null
    OLDURL=$URL
    CODE=`cat html | gawk '/HTTP/{ print $2}' | tail -n 1`
    URL=`cat html | gawk '/Location/{ print $2}' | tail -n 1`
    if [[ "$URL" == "" ]]; then
        URL=$OLDURL
        sleep 30
    fi
    echo "Loading interrupted ($CODE): $URL"
done


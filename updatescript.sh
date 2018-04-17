#!/bin/bash
remotehost=seednodeIP
jsonpath=~/tmp/qtumpeerinfo.json
/usr/local/bin/qtum-cli getpeerinfo > ~/tmp/qtumpeerinfo.json
scp $jsonpath node@$remotehost:~/data/qtumpeerinfo0.json
rm $jsonpath

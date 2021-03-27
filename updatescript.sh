#!/bin/bash
remotehost=cronserver
jsonpath=~/tmp/ccspeerinfo.json
/usr/local/bin/ccs-cli getpeerinfo > ~/tmp/ccspeerinfo.json
scp $jsonpath node@$remotehost:~/data/ccspeerinfo.json
rm $jsonpath

#!/bin/bash

set -x

url = ${URL01}
while read -r LINE
do
  curl ${URL01}${LINE}
done < /tmp/data.txt

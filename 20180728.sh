#!/bin/bash

set -x

url = ${URL01}
while read -r LINE
do
  curl -u ${U1}:${P1} ${URL01}${LINE}/
done < /tmp/data.txt

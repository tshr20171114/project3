#!/bin/bash

set -x

flag='ON'
url=${URL01}
while read -r LINE
do
  if [ $flag = 'ON' ]; then
    curl -u ${U1}:${P1} ${URL01}${LINE}/ &
    flag='OFF'
  else
    curl -u ${U1}:${P1} ${URL01}${LINE}/
    flag='ON'
    wait
  fi
done < /tmp/data.txt

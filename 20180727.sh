#!/bin/bash

set -x

n=0
url=${URL01}
while read -r LINE
do
  if [ n -eq 0 ]; then
    wait
  fi
  curl -u ${U1}:${P1} ${URL01}${LINE}/ &
  echo $((n+=1))
  echo $((n%=4))
done < /tmp/data.txt

wait

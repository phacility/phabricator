#!/bin/sh

diff "$@"
if [ "$?" = "2" ]; then
  exit 1
fi

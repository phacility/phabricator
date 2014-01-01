#!/bin/sh

echo "resources/celerity/map.php merge=celerity" \
  >> `dirname "$0"`/../../.git/info/attributes

git config merge.celerity.name "Celerity Mapper"

git config merge.celerity.driver \
  'php $GIT_DIR/../bin/celerity map'

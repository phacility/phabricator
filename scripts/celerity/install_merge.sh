#!/bin/sh

echo "src/__celerity_resource_map__.php merge=celerity" \
  >> `dirname "$0"`/../../.git/info/attributes

git config merge.celerity.name "Celerity Mapper"

git config merge.celerity.driver \
  'php $GIT_DIR/../scripts/celerity_mapper.php $GIT_DIR/../webroot'

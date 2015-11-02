<?php

final class HarbormasterExternalBuildStepGroup
  extends HarbormasterBuildStepGroup {

  const GROUPKEY = 'harbormaster.external';

  public function getGroupName() {
    return pht('Interacting with External Build Sytems');
  }

  public function getGroupOrder() {
    return 4000;
  }

}

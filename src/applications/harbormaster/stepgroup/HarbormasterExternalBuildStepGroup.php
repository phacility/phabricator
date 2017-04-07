<?php

final class HarbormasterExternalBuildStepGroup
  extends HarbormasterBuildStepGroup {

  const GROUPKEY = 'harbormaster.external';

  public function getGroupName() {
    return pht('Interacting with External Build Systems');
  }

  public function getGroupOrder() {
    return 4000;
  }

}

<?php

final class HarbormasterControlBuildStepGroup
  extends HarbormasterBuildStepGroup {

  const GROUPKEY = 'harbormaster.control';

  public function getGroupName() {
    return pht('Flow Control');
  }

  public function getGroupOrder() {
    return 5000;
  }

  public function shouldShowIfEmpty() {
    return false;
  }

}

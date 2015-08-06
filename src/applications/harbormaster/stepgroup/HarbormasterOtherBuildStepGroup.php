<?php

final class HarbormasterOtherBuildStepGroup
  extends HarbormasterBuildStepGroup {

  const GROUPKEY = 'harbormaster.other';

  public function getGroupName() {
    return pht('Other Build Steps');
  }

  public function getGroupOrder() {
    return 9000;
  }

  public function shouldShowIfEmpty() {
    return false;
  }

}

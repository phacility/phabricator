<?php

final class HarbormasterTestBuildStepGroup
  extends HarbormasterBuildStepGroup {

  const GROUPKEY = 'harbormaster.test';

  public function getGroupName() {
    return pht('Testing Utilities');
  }

  public function getGroupOrder() {
    return 7000;
  }

}

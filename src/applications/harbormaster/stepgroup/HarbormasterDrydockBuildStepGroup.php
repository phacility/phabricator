<?php

final class HarbormasterDrydockBuildStepGroup
  extends HarbormasterBuildStepGroup {

  const GROUPKEY = 'harbormaster.drydock';

  public function getGroupName() {
    return pht('Drydock');
  }

  public function getGroupOrder() {
    return 3000;
  }

  public function isEnabled() {
    $drydock_class = 'PhabricatorDrydockApplication';
    return PhabricatorApplication::isClassInstalled($drydock_class);
  }

  public function shouldShowIfEmpty() {
    return false;
  }

}

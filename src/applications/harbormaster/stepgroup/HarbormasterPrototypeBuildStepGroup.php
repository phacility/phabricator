<?php

final class HarbormasterPrototypeBuildStepGroup
  extends HarbormasterBuildStepGroup {

  const GROUPKEY = 'harbormaster.prototype';

  public function getGroupName() {
    return pht('Prototypes');
  }

  public function getGroupOrder() {
    return 8000;
  }

  public function isEnabled() {
    return PhabricatorEnv::getEnvConfig('phabricator.show-prototypes');
  }

  public function shouldShowIfEmpty() {
    return false;
  }

}

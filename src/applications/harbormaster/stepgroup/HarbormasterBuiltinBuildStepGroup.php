<?php

final class HarbormasterBuiltinBuildStepGroup
  extends HarbormasterBuildStepGroup {

  const GROUPKEY = 'harbormaster.builtin';

  public function getGroupName() {
    return pht('Builtins');
  }

  public function getGroupOrder() {
    return 0;
  }

  public function shouldShowIfEmpty() {
    return false;
  }

}

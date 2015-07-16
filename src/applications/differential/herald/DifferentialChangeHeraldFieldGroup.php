<?php

final class DifferentialChangeHeraldFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'differential.change';

  public function getGroupLabel() {
    return pht('Change Details');
  }

  protected function getGroupOrder() {
    return 1500;
  }

}

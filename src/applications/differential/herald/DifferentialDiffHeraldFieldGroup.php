<?php

final class DifferentialDiffHeraldFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'differential.diff';

  public function getGroupLabel() {
    return pht('Diff Fields');
  }

  protected function getGroupOrder() {
    return 1000;
  }

}

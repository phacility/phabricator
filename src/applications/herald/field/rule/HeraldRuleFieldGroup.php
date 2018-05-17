<?php

final class HeraldRuleFieldGroup
  extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'herald.rule';

  public function getGroupLabel() {
    return pht('Rule Fields');
  }

  protected function getGroupOrder() {
    return 500;
  }

}

<?php

final class HeraldBasicFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'herald';

  public function getGroupLabel() {
    return pht('Herald');
  }

  protected function getGroupOrder() {
    return 10000;
  }

}

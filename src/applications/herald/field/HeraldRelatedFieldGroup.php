<?php

final class HeraldRelatedFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'related';

  public function getGroupLabel() {
    return pht('Related Fields');
  }

  protected function getGroupOrder() {
    return 2000;
  }

}

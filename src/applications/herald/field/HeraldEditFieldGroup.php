<?php

final class HeraldEditFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'edit';

  public function getGroupLabel() {
    return pht('Edit Attributes');
  }

  protected function getGroupOrder() {
    return 4000;
  }

}

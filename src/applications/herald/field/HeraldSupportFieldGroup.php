<?php

final class HeraldSupportFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'support';

  public function getGroupLabel() {
    return pht('Supporting Applications');
  }

  protected function getGroupOrder() {
    return 3000;
  }

}

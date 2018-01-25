<?php

final class HeraldDeprecatedFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'deprecated';

  public function getGroupLabel() {
    return pht('Deprecated');
  }

  protected function getGroupOrder() {
    return 99999;
  }

}

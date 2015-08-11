<?php

final class ManiphestTaskHeraldFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'maniphest.task';

  public function getGroupLabel() {
    return pht('Task Fields');
  }

  protected function getGroupOrder() {
    return 500;
  }

}

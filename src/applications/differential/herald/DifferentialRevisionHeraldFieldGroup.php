<?php

final class DifferentialRevisionHeraldFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'differential.revision';

  public function getGroupLabel() {
    return pht('Revision Fields');
  }

  protected function getGroupOrder() {
    return 1000;
  }

}

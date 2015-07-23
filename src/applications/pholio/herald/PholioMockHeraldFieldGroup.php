<?php

final class PholioMockHeraldFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'pholio.mock';

  public function getGroupLabel() {
    return pht('Mock Fields');
  }

  protected function getGroupOrder() {
    return 1000;
  }

}

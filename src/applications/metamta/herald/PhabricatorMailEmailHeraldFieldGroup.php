<?php

final class PhabricatorMailEmailHeraldFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'mail.message';

  public function getGroupLabel() {
    return pht('Message Fields');
  }

  protected function getGroupOrder() {
    return 1000;
  }

}

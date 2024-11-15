<?php

final class PhabricatorRoleHeraldFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'roles.role';

  public function getGroupLabel() {
    return pht('Role Fields');
  }

  protected function getGroupOrder() {
    return 500;
  }

}

<?php

final class PhabricatorProjectHeraldFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'projects.project';

  public function getGroupLabel() {
    return pht('Project Fields');
  }

  protected function getGroupOrder() {
    return 500;
  }

}

<?php

/**
 * @group maniphest
 */
final class ManiphestRemarkupRule
  extends PhabricatorRemarkupRuleObject {

  protected function getObjectNamePrefix() {
    return 'T';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    return id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withTaskIDs($ids)
      ->execute();
  }

}

<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleDifferentialHandle
  extends PhabricatorRemarkupRuleObjectHandle {

  protected function getObjectNamePrefix() {
    return 'D';
  }

  protected function loadObjectPHID($id) {
    $revision = id(new DifferentialRevision())->load($id);
    if ($revision) {
      return $revision->getPHID();
    }
    return null;
  }

}

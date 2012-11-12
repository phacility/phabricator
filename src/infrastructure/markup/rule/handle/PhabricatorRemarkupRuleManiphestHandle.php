<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleManiphestHandle
  extends PhabricatorRemarkupRuleObjectHandle {

  protected function getObjectNamePrefix() {
    return 'T';
  }

  protected function loadObjectPHID($id) {
    $task = id(new ManiphestTask())->load($id);
    if ($task) {
      return $task->getPHID();
    }
    return null;
  }

}

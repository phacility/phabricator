<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRulePaste
  extends PhabricatorRemarkupRuleObjectName {

  protected function getObjectNamePrefix() {
    return 'P';
  }

}

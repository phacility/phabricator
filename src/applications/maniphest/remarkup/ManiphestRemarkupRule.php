<?php

/**
 * @group markup
 */
final class ManiphestRemarkupRule
  extends PhabricatorRemarkupRuleObjectName {

  protected function getObjectNamePrefix() {
    return 'T';
  }

}

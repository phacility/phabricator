<?php

/**
 * @group markup
 */
final class DifferentialRemarkupRule
  extends PhabricatorRemarkupRuleObjectName {

  protected function getObjectNamePrefix() {
    return 'D';
  }

}

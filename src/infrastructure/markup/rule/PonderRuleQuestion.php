<?php

/**
 * @group markup
 */
final class PonderRuleQuestion
  extends PhabricatorRemarkupRuleObjectName {

  protected function getObjectNamePrefix() {
    return 'Q(?![1-4]\b)';
  }
}


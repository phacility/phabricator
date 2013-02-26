<?php

/**
 * @group markup
 */
final class PonderRemarkupRule
  extends PhabricatorRemarkupRuleObjectName {

  protected function getObjectNamePrefix() {
    return 'Q(?![1-4]\b)';
  }
}


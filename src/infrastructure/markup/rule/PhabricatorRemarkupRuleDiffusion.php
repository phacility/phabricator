<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleDiffusion
  extends PhabricatorRemarkupRuleObjectName {

  protected function getObjectNamePrefix() {
    return 'r';
  }

  protected function getObjectIDPattern() {
    return '[A-Z]+[a-f0-9]+';
  }

}

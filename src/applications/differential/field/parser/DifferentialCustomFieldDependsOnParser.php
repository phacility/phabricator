<?php

final class DifferentialCustomFieldDependsOnParser
  extends PhabricatorCustomFieldMonogramParser {

  protected function getPrefixes() {
    return array(
      'depends on',
    );
  }

  protected function getInfixes() {
    return array(
      'diff',
      'diffs',
      'change',
      'changes',
      'rev',
      'revs',
      'revision',
    );
  }

  protected function getSuffixes() {
    return array();
  }

  protected function getMonogramPattern() {
    return '[Dd]\d+';
  }

}

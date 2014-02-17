<?php

final class ManiphestCustomFieldStatusParser
  extends PhabricatorCustomFieldMonogramParser {

  protected function getPrefixes() {
    return array_keys(ManiphestTaskStatus::getStatusPrefixMap());
  }

  protected function getInfixes() {
    return array(
      'task',
      'tasks',
      'issue',
      'issues',
      'bug',
      'bugs',
    );
  }

  protected function getSuffixes() {
    return array_keys(ManiphestTaskStatus::getStatusSuffixMap());
  }

  protected function getMonogramPattern() {
    return '[tT]\d+';
  }

}

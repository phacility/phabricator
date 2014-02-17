<?php

final class ManiphestCustomFieldStatusParser
  extends PhabricatorCustomFieldMonogramParser {

  protected function getPrefixes() {
    return array(
      'resolve',
      'resolves',
      'resolved',
      'fix',
      'fixes',
      'fixed',
      'wontfix',
      'wontfixes',
      'wontfixed',
      'spite',
      'spites',
      'spited',
      'invalidate',
      'invalidates',
      'invalidated',
      'close',
      'closes',
      'closed',
      'ref',
      'refs',
      'references',
      'cf.',
    );
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
    return array(
      'as resolved',
      'as fixed',
      'as wontfix',
      'as spite',
      'out of spite',
      'as invalid',
    );
  }

  protected function getMonogramPattern() {
    return '[tT]\d+';
  }

}

<?php

final class DifferentialCustomFieldRevertsParser
  extends PhabricatorCustomFieldMonogramParser {

  protected function getPrefixes() {

    // NOTE: Git language is "This reverts commit X."
    // NOTE: Mercurial language is "Backed out changeset Y".

    return array(
      'revert',
      'reverts',
      'reverted',
      'backout',
      'backsout',
      'backedout',
      'back out',
      'backs out',
      'backed out',
      'undo',
      'undoes',
    );
  }

  protected function getInfixes() {
    return array(
      'commit',
      'commits',
      'change',
      'changes',
      'changeset',
      'changesets',
      'rev',
      'revs',
      'revision',
      'revisions',
      'diff',
      'diffs',
    );
  }

  protected function getSuffixes() {
    return array();
  }

  protected function getMonogramPattern() {
    return '[rA-Z0-9a-f]+';
  }

}

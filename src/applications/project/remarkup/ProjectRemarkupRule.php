<?php

final class ProjectRemarkupRule extends PhabricatorObjectRemarkupRule {

  protected function getObjectNamePrefix() {
    return '#';
  }

  protected function renderObjectRef(
    $object,
    PhabricatorObjectHandle $handle,
    $anchor,
    $id) {

    if ($this->getEngine()->isTextMode()) {
      return '#'.$id;
    }

    return $handle->renderTag();
  }

  protected function getObjectIDPattern() {
    // NOTE: This explicitly does not match strings which contain only
    // digits, because digit strings like "#123" are used to reference tasks at
    // Facebook and are somewhat conventional in general.

    // The latter half of this rule matches monograms with internal periods,
    // like `#domain.com`, but does not match monograms with terminal periods,
    // because they're probably just puncutation.

    // Broadly, this will not match every possible project monogram, and we
    // accept some false negatives -- like `#1` or `#dot.` -- in order to avoid
    // a bunch of false positives on general use of the `#` character.

    // In other contexts, the PhabricatorProjectProjectPHIDType pattern is
    // controlling and these names should parse correctly.

    // These characters may never appear anywhere in a hashtag.
    $never = '\s?!,:;{}#\\(\\)"\'';

    // These characters may not appear at the beginning.
    $never_first = '.\d';

    // These characters may not appear at the end.
    $never_last = '.';

    return
      '[^'.$never_first.$never.']+'.
      '(?:'.
        '[^'.$never.']*'.
        '[^'.$never_last.$never.']+'.
      ')*';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    // Put the "#" back on the front of these IDs.
    $names = array();
    foreach ($ids as $id) {
      $names[] = '#'.$id;
    }

    // Issue a query by object name.
    $query = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames($names);

    $query->execute();
    $projects = $query->getNamedResults();

    // Slice the "#" off again.
    $result = array();
    foreach ($projects as $name => $project) {
      $result[substr($name, 1)] = $project;
    }

    return $result;
  }

}

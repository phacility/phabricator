<?php

final class DiffusionRemarkupRule
  extends PhabricatorRemarkupRuleObject {

  protected function getObjectNamePrefix() {
    return '';
  }

  protected function getObjectIDPattern() {
    $min_unqualified = PhabricatorRepository::MINIMUM_UNQUALIFIED_HASH;
    $min_qualified   = PhabricatorRepository::MINIMUM_QUALIFIED_HASH;

    return
      'r[A-Z]+[1-9]\d*'.
      '|'.
      'r[A-Z]+[a-f0-9]{'.$min_qualified.',40}'.
      '|'.
      '[a-f0-9]{'.$min_unqualified.',40}';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');
    $min_qualified = PhabricatorRepository::MINIMUM_QUALIFIED_HASH;

    $commits = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIdentifiers($ids)
      ->execute();

    if (!$commits) {
      return array();
    }

    $ids = array_fuse($ids);

    $result = array();
    foreach ($commits as $commit) {
      $prefix = 'r'.$commit->getRepository()->getCallsign();
      $suffix = $commit->getCommitIdentifier();

      if ($commit->getRepository()->isSVN()) {
        if (isset($ids[$prefix.$suffix])) {
          $result[$prefix.$suffix][] = $commit;
        }
      } else {
        // This awkward contruction is so we can link the commits up in O(N)
        // time instead of O(N^2).
        for ($ii = $min_qualified; $ii <= strlen($suffix); $ii++) {
          $part = substr($suffix, 0, $ii);
          if (isset($ids[$prefix.$part])) {
            $result[$prefix.$part][] = $commit;
          }
          if (isset($ids[$part])) {
            $result[$part][] = $commit;
          }
        }
      }
    }

    foreach ($result as $identifier => $commits) {
      if (count($commits) == 1) {
        $result[$identifier] = head($commits);
      } else {
        // This reference is ambiguous -- it matches more than one commit -- so
        // don't link it. We could potentially improve this, but it's a bit
        // tricky since the superclass expects a single object.
        unset($result[$identifier]);
      }
    }

    return $result;
  }

}

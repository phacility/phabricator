<?php

abstract class PhabricatorPackagesQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  public function getQueryApplicationClass() {
    return 'PhabricatorPackagesApplication';
  }

  protected function buildFullKeyClauseParts(
    AphrontDatabaseConnection $conn,
    array $full_keys) {

    $parts = array();
    foreach ($full_keys as $full_key) {
      $key_parts = explode('/', $full_key, 2);

      if (count($key_parts) != 2) {
        continue;
      }

      $parts[] = qsprintf(
        $conn,
        '(u.publisherKey = %s AND p.packageKey = %s)',
        $key_parts[0],
        $key_parts[1]);
    }

    // If none of the full keys we were provided were valid, we don't
    // match any results.
    if (!$parts) {
      throw new PhabricatorEmptyQueryException();
    }

    return implode(' OR ', $parts);
  }

}

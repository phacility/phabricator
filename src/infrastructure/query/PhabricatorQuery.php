<?php

abstract class PhabricatorQuery {

  abstract public function execute();

  final protected function formatWhereClause(array $parts) {
    $parts = array_filter($parts);

    if (!$parts) {
      return '';
    }

    return 'WHERE ('.implode(') AND (', $parts).')';
  }

}

<?php

/**
 * @task format Formatting Query Clauses
 */
abstract class PhabricatorQuery {


  abstract public function execute();


/* -(  Formatting Query Clauses  )------------------------------------------- */


  /**
   * @task format
   */
  protected function formatWhereClause(array $parts) {
    $parts = $this->flattenSubclause($parts);
    if (!$parts) {
      return '';
    }

    return 'WHERE '.$this->formatWhereSubclause($parts);
  }


  /**
   * @task format
   */
  protected function formatWhereSubclause(array $parts) {
    $parts = $this->flattenSubclause($parts);
    if (!$parts) {
      return null;
    }

    return '('.implode(') AND (', $parts).')';
  }


  /**
   * @task format
   */
  protected function formatSelectClause(array $parts) {
    $parts = $this->flattenSubclause($parts);
    if (!$parts) {
      throw new Exception(pht('Can not build empty select clause!'));
    }

    return 'SELECT '.$this->formatSelectSubclause($parts);
  }


  /**
   * @task format
   */
  protected function formatSelectSubclause(array $parts) {
    $parts = $this->flattenSubclause($parts);
    if (!$parts) {
      return null;
    }
    return implode(', ', $parts);
  }


  /**
   * @task format
   */
  protected function formatJoinClause(array $parts) {
    $parts = $this->flattenSubclause($parts);
    if (!$parts) {
      return '';
    }

    return implode(' ', $parts);
  }


  /**
   * @task format
   */
  protected function formatHavingClause(array $parts) {
    $parts = $this->flattenSubclause($parts);
    if (!$parts) {
      return '';
    }

    return 'HAVING '.$this->formatHavingSubclause($parts);
  }


  /**
   * @task format
   */
  protected function formatHavingSubclause(array $parts) {
    $parts = $this->flattenSubclause($parts);
    if (!$parts) {
      return null;
    }

    return '('.implode(') AND (', $parts).')';
  }


  /**
   * @task format
   */
  private function flattenSubclause(array $parts) {
    $result = array();
    foreach ($parts as $part) {
      if (is_array($part)) {
        foreach ($this->flattenSubclause($part) as $subpart) {
          $result[] = $subpart;
        }
      } else if (strlen($part)) {
        $result[] = $part;
      }
    }
    return $result;
  }

}

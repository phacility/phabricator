<?php

/**
 * @task format Formatting Query Clauses
 */
abstract class PhabricatorQuery extends Phobject {


  abstract public function execute();


/* -(  Formatting Query Clauses  )------------------------------------------- */


  /**
   * @task format
   */
  protected function formatWhereClause(
    AphrontDatabaseConnection $conn,
    array $parts) {

    $parts = $this->flattenSubclause($parts);
    if (!$parts) {
      return qsprintf($conn, '');
    }

    return qsprintf($conn, 'WHERE %LA', $parts);
  }



  /**
   * @task format
   */
  protected function formatSelectClause(
    AphrontDatabaseConnection $conn,
    array $parts) {

    $parts = $this->flattenSubclause($parts);
    if (!$parts) {
      throw new Exception(pht('Can not build empty SELECT clause!'));
    }

    return qsprintf($conn, 'SELECT %LQ', $parts);
  }


  /**
   * @task format
   */
  protected function formatJoinClause(
    AphrontDatabaseConnection $conn,
    array $parts) {

    $parts = $this->flattenSubclause($parts);
    if (!$parts) {
      return qsprintf($conn, '');
    }

    return qsprintf($conn, '%LJ', $parts);
  }


  /**
   * @task format
   */
  protected function formatHavingClause(
    AphrontDatabaseConnection $conn,
    array $parts) {

    $parts = $this->flattenSubclause($parts);
    if (!$parts) {
      return qsprintf($conn, '');
    }

    return qsprintf($conn, 'HAVING %LA', $parts);
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
      } else if (($part !== null) && strlen($part)) {
        $result[] = $part;
      }
    }
    return $result;
  }

}

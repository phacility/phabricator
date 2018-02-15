<?php

final class PhabricatorTypeaheadTestNumbersDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Numbers');
  }

  public function getPlaceholderText() {
    return null;
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  public function getDatasourceFunctions() {
    return array(
      'seven' => array(),
      'inc' => array(),
      'sum' => array(),
    );
  }

  public function loadResults() {
    return array();
  }

  public function renderFunctionTokens($function, array $argv_list) {
    return array();
  }

  protected function evaluateFunction($function, array $argv_list) {
    $results = array();

    foreach ($argv_list as $argv) {
      foreach ($argv as $k => $arg) {
        if (!is_scalar($arg) || !preg_match('/^\d+\z/', $arg)) {
          throw new PhabricatorTypeaheadInvalidTokenException(
            pht(
              'All arguments to "%s(...)" must be integers, found '.
              '"%s" in position %d.',
              $function,
              (is_scalar($arg) ? $arg : gettype($arg)),
              $k + 1));
        }
        $argv[$k] = (int)$arg;
      }

      switch ($function) {
        case 'seven':
          $results[] = 7;
          break;
        case 'inc':
          $results[] = $argv[0] + 1;
          break;
        case 'sum':
          $results[] = array_sum($argv);
          break;
      }
    }

    return $results;
  }

}

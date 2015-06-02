<?php

abstract class ManiphestExcelFormat {

  final public static function loadAllFormats() {
    $classes = id(new PhutilSymbolLoader())
      ->setAncestorClass(__CLASS__)
      ->setConcreteOnly(true)
      ->selectAndLoadSymbols();

    $objects = array();
    foreach ($classes as $class) {
      $objects[$class['name']] = newv($class['name'], array());
    }

    $objects = msort($objects, 'getOrder');

    return $objects;
  }

  abstract public function getName();
  abstract public function getFileName();

  public function getOrder() {
    return 0;
  }

  protected function computeExcelDate($epoch) {
    $seconds_per_day = (60 * 60 * 24);
    $offset = ($seconds_per_day * 25569);

    return ($epoch + $offset) / $seconds_per_day;
  }

  /**
   * @phutil-external-symbol class PHPExcel
   */
  abstract public function buildWorkbook(
    PHPExcel $workbook,
    array $tasks,
    array $handles,
    PhabricatorUser $user);

}

<?php

abstract class ManiphestExcelFormat extends Phobject {

  final public static function loadAllFormats() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setSortMethod('getOrder')
      ->execute();
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

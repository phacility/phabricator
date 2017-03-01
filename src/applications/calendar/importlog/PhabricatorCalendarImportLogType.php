<?php

abstract class PhabricatorCalendarImportLogType
  extends Phobject {

  final public function getLogTypeConstant() {
    return $this->getPhobjectClassConstant('LOGTYPE', 64);
  }

  final public static function getAllLogTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getLogTypeConstant')
      ->execute();
  }

  abstract public function getDisplayType(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log);

  public function getDisplayIcon(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'fa-warning';
  }

  public function getDisplayColor(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return 'yellow';
  }

  public function getDisplayDescription(
    PhabricatorUser $viewer,
    PhabricatorCalendarImportLog $log) {
    return null;
  }

}

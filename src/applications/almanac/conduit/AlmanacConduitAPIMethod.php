<?php

abstract class AlmanacConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorAlmanacApplication');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht(
      'Almanac is a prototype application and its APIs are '.
      'subject to change.');
  }

}

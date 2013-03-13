<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_phid_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationPHID');
  }

  protected function buildHandleInformationDictionary(
    PhabricatorObjectHandle $handle) {

    return array(
      'phid'      => $handle->getPHID(),
      'uri'       => PhabricatorEnv::getProductionURI($handle->getURI()),

      'typeName'  => $handle->getTypeName(),
      'type'      => $handle->getType(),

      'name'      => $handle->getName(),
      'fullName'  => $handle->getFullName(),

      'status'    => $handle->getStatus(),
    );
  }

}

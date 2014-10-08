<?php

abstract class PHIDConduitAPIMethod extends ConduitAPIMethod {

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

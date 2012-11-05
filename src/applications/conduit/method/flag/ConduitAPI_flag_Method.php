<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_flag_Method extends ConduitAPIMethod {

  protected function attachHandleToFlag($flag) {
    $flag->attachHandle(
      PhabricatorObjectHandleData::loadOneHandle($flag->getObjectPHID())
    );
  }

  protected function buildFlagInfoDictionary($flag) {
    $color = $flag->getColor();
    $uri = PhabricatorEnv::getProductionURI($flag->getHandle()->getURI());

    return array(
      'id'            => $flag->getID(),
      'ownerPHID'     => $flag->getOwnerPHID(),
      'type'          => $flag->getType(),
      'objectPHID'    => $flag->getObjectPHID(),
      'reasonPHID'    => $flag->getReasonPHID(),
      'color'         => $color,
      'colorName'     => PhabricatorFlagColor::getColorName($color),
      'note'          => $flag->getNote(),
      'handle'        => array(
        'uri'         => $uri,
        'name'        => $flag->getHandle()->getName(),
        'fullname'    => $flag->getHandle()->getFullName(),
      ),
      'dateCreated'   => $flag->getDateCreated(),
      'dateModified'  => $flag->getDateModified(),
    );
  }

}

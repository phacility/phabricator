<?php

abstract class FlagConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorFlagsApplication');
  }

  protected function attachHandleToFlag($flag, PhabricatorUser $user) {
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($flag->getObjectPHID()))
      ->executeOne();
    $flag->attachHandle($handle);
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
        'uri'      => $uri,
        'name'     => $flag->getHandle()->getName(),
        'fullname' => $flag->getHandle()->getFullName(),
      ),
      'dateCreated'   => $flag->getDateCreated(),
      'dateModified'  => $flag->getDateModified(),
    );
  }

}

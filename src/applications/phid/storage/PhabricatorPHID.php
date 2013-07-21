<?php

final class PhabricatorPHID {

  protected $phid;
  protected $phidType;
  protected $ownerPHID;
  protected $parentPHID;

  public static function generateNewPHID($type, $subtype = null) {
    if (!$type) {
      throw new Exception("Can not generate PHID with no type.");
    }

    if ($subtype === null) {
      $uniq_len = 20;
      $type_str = "{$type}";
    } else {
      $uniq_len = 15;
      $type_str = "{$type}-{$subtype}";
    }

    $uniq = Filesystem::readRandomCharacters($uniq_len);
    return "PHID-{$type_str}-{$uniq}";
  }

  public static function fromObjectName($name, PhabricatorUser $viewer) {
    $query = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames(array($name));
    $query->execute();

    $objects = $query->getNamedResults();
    if ($objects) {
      return head($objects)->getPHID();
    }

    /// TODO: Destroy this legacy stuff.

    $object = null;
    $match = null;
    if (preg_match('/^PHID-[A-Z]+-.{20}$/', $name)) {
      // It's already a PHID! Yay.
      return $name;
    }

    if (preg_match('/^m(\d+)$/i', $name, $match)) {
      $objects = id(new PholioMockQuery())
        ->setViewer($viewer)
        ->withIDs(array($match[1]))
        ->execute();
      $object = head($objects);
    }

    if ($object) {
      return $object->getPHID();
    }

    return null;
  }
}

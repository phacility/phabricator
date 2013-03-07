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
    $object = null;
    $match = null;
    if (preg_match('/^PHID-[A-Z]+-.{20}$/', $name)) {
      // It's already a PHID! Yay.
      return $name;
    }
    if (preg_match('/^r([A-Z]+)(\S*)$/', $name, $match)) {
      $repository = id(new PhabricatorRepository())
        ->loadOneWhere('callsign = %s', $match[1]);
      if ($match[2] == '') {
        $object = $repository;
      } else if ($repository) {
        $object = id(new PhabricatorRepositoryCommit())->loadOneWhere(
          'repositoryID = %d AND commitIdentifier = %s',
          $repository->getID(),
          $match[2]);
        if (!$object) {
          try {
            $object = id(new PhabricatorRepositoryCommit())->loadOneWhere(
              'repositoryID = %d AND commitIdentifier LIKE %>',
              $repository->getID(),
              $match[2]);
          } catch (AphrontQueryCountException $ex) {
            // Ambiguous; return nothing.
          }
        }
      }
    } else if (preg_match('/^d(\d+)$/i', $name, $match)) {
      $object = id(new DifferentialRevision())->load($match[1]);
    } else if (preg_match('/^t(\d+)$/i', $name, $match)) {
      $object = id(new ManiphestTask())->load($match[1]);
    } else if (preg_match('/^m(\d+)$/i', $name, $match)) {
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

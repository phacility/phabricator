<?php

final class PhabricatorPHID {

  protected $phid;
  protected $phidType;
  protected $ownerPHID;
  protected $parentPHID;

  public static function generateNewPHID($type) {
    if (!$type) {
      throw new Exception("Can not generate PHID with no type.");
    }

    $uniq = Filesystem::readRandomCharacters(20);
    return 'PHID-'.$type.'-'.$uniq;
  }

  public static function fromObjectName($name) {
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
    }
    if ($object) {
      return $object->getPHID();
    }
    return null;
  }
}

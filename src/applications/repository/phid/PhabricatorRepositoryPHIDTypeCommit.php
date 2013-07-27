<?php

final class PhabricatorRepositoryPHIDTypeCommit extends PhabricatorPHIDType {

  const TYPECONST = 'CMIT';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Commit');
  }

  public function newObject() {
    return new PhabricatorRepositoryCommit();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DiffusionCommitQuery())
      ->setViewer($query->getViewer())
      ->withPHIDs($phids)
      ->execute();
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $commit = $objects[$phid];
      $repository = $commit->getRepository();
      $callsign = $repository->getCallsign();
      $commit_identifier = $commit->getCommitIdentifier();

      $name = $repository->formatCommitName($commit_identifier);
      $summary = $commit->getSummary();
      if (strlen($summary)) {
        $full_name = $name.': '.$summary;
      } else {
        $full_name = $name;
      }

      $handle->setName($name);
      $handle->setFullName($full_name);
      $handle->setURI('/r'.$callsign.$commit_identifier);
      $handle->setTimestamp($commit->getEpoch());
    }
  }

  public static function getCommitObjectNamePattern() {
    $min_unqualified = PhabricatorRepository::MINIMUM_UNQUALIFIED_HASH;
    $min_qualified   = PhabricatorRepository::MINIMUM_QUALIFIED_HASH;

    return
      'r[A-Z]+[1-9]\d*'.
      '|'.
      'r[A-Z]+[a-f0-9]{'.$min_qualified.',40}'.
      '|'.
      '[a-f0-9]{'.$min_unqualified.',40}';
  }


  public function canLoadNamedObject($name) {
    $pattern = self::getCommitObjectNamePattern();
    return preg_match('(^'.$pattern.'$)', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $query = id(new DiffusionCommitQuery())
      ->setViewer($query->getViewer())
      ->withIdentifiers($names);

    $query->execute();

    return $query->getIdentifierMap();
  }

}

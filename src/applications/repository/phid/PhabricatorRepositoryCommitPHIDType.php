<?php

final class PhabricatorRepositoryCommitPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'CMIT';

  public function getTypeName() {
    return pht('Diffusion Commit');
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function newObject() {
    return new PhabricatorRepositoryCommit();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DiffusionCommitQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $commit = $objects[$phid];
      $repository = $commit->getRepository();
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
      $handle->setURI($commit->getURI());
      $handle->setTimestamp($commit->getEpoch());
    }
  }

  public static function getCommitObjectNamePattern() {
    $min_unqualified = PhabricatorRepository::MINIMUM_UNQUALIFIED_HASH;
    $min_qualified   = PhabricatorRepository::MINIMUM_QUALIFIED_HASH;

    return
      '(?:r[A-Z]+:?|R[0-9]+:)[1-9]\d*'.
      '|'.
      '(?:r[A-Z]+:?|R[0-9]+:)[a-f0-9]{'.$min_qualified.',40}'.
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

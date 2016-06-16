<?php

/**
 * @task serialization Serializing Repository Refs
 */
final class DiffusionRepositoryRef extends Phobject {

  private $shortName;
  private $commitIdentifier;
  private $refType;
  private $rawFields = array();

  public function setRawFields(array $raw_fields) {
    $this->rawFields = $raw_fields;
    return $this;
  }

  public function getRawFields() {
    return $this->rawFields;
  }

  public function setCommitIdentifier($commit_identifier) {
    $this->commitIdentifier = $commit_identifier;
    return $this;
  }

  public function getCommitIdentifier() {
    return $this->commitIdentifier;
  }

  public function setShortName($short_name) {
    $this->shortName = $short_name;
    return $this;
  }

  public function getShortName() {
    return $this->shortName;
  }

  public function setRefType($ref_type) {
    $this->refType = $ref_type;
    return $this;
  }

  public function getRefType() {
    return $this->refType;
  }

  public function isBranch() {
    $type_branch = PhabricatorRepositoryRefCursor::TYPE_BRANCH;
    return ($this->getRefType() === $type_branch);
  }

  public function isTag() {
    $type_tag = PhabricatorRepositoryRefCursor::TYPE_TAG;
    return ($this->getRefType() === $type_tag);
  }


/* -(  Serialization  )------------------------------------------------------ */


  public function toDictionary() {
    return array(
      'shortName' => $this->shortName,
      'commitIdentifier' => $this->commitIdentifier,
      'refType' => $this->refType,
      'rawFields' => $this->rawFields,
    );
  }

  public static function newFromDictionary(array $dict) {
    return id(new DiffusionRepositoryRef())
      ->setShortName($dict['shortName'])
      ->setCommitIdentifier($dict['commitIdentifier'])
      ->setRefType($dict['refType'])
      ->setRawFields($dict['rawFields']);
  }

  public static function loadAllFromDictionaries(array $dictionaries) {
    $refs = array();
    foreach ($dictionaries as $dictionary) {
      $refs[] = self::newFromDictionary($dictionary);
    }
    return $refs;
  }

}

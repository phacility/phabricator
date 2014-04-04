<?php

/**
 * @task serialization Serializing Repository Refs
 */
final class DiffusionRepositoryRef extends Phobject {

  private $shortName;
  private $commitIdentifier;
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


/* -(  Serialization  )------------------------------------------------------ */


  public function toDictionary() {
    return array(
      'shortName' => $this->shortName,
      'commitIdentifier' => $this->commitIdentifier,
      'rawFields' => $this->rawFields,
    );
  }

  public static function newFromDictionary(array $dict) {
    return id(new DiffusionRepositoryRef())
      ->setShortName($dict['shortName'])
      ->setCommitIdentifier($dict['commitIdentifier'])
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

<?php

final class DiffusionRepositoryRef {

  private $shortName;
  private $commitIdentifier;
  private $rawFields;

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

}

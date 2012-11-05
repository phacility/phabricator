<?php

final class DiffusionRepositoryTag {

  private $author;
  private $epoch;
  private $commitIdentifier;
  private $name;
  private $description;
  private $type;

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setCommitIdentifier($commit_identifier) {
    $this->commitIdentifier = $commit_identifier;
    return $this;
  }

  public function getCommitIdentifier() {
    return $this->commitIdentifier;
  }

  public function setEpoch($epoch) {
    $this->epoch = $epoch;
    return $this;
  }

  public function getEpoch() {
    return $this->epoch;
  }

  public function setAuthor($author) {
    $this->author = $author;
    return $this;
  }

  public function getAuthor() {
    return $this->author;
  }

}

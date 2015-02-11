<?php

final class DiffusionCommitRef extends Phobject {

  private $message;
  private $authorName;
  private $authorEmail;
  private $committerName;
  private $committerEmail;
  private $hashes = array();

  public static function newFromConduitResult(array $result) {
    $ref = id(new DiffusionCommitRef())
      ->setCommitterEmail(idx($result, 'committerEmail'))
      ->setCommitterName(idx($result, 'committerName'))
      ->setAuthorEmail(idx($result, 'authorEmail'))
      ->setAuthorName(idx($result, 'authorName'))
      ->setMessage(idx($result, 'message'));

    $hashes = array();
    foreach (idx($result, 'hashes', array()) as $hash_result) {
      $hashes[] = id(new DiffusionCommitHash())
        ->setHashType(idx($hash_result, 'type'))
        ->setHashValue(idx($hash_result, 'value'));
    }

    $ref->setHashes($hashes);

    return $ref;
  }

  public function setHashes(array $hashes) {
    $this->hashes = $hashes;
    return $this;
  }

  public function getHashes() {
    return $this->hashes;
  }

  public function setCommitterEmail($committer_email) {
    $this->committerEmail = $committer_email;
    return $this;
  }

  public function getCommitterEmail() {
    return $this->committerEmail;
  }


  public function setCommitterName($committer_name) {
    $this->committerName = $committer_name;
    return $this;
  }

  public function getCommitterName() {
    return $this->committerName;
  }


  public function setAuthorEmail($author_email) {
    $this->authorEmail = $author_email;
    return $this;
  }

  public function getAuthorEmail() {
    return $this->authorEmail;
  }


  public function setAuthorName($author_name) {
    $this->authorName = $author_name;
    return $this;
  }

  public function getAuthorName() {
    return $this->authorName;
  }

  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  public function getMessage() {
    return $this->message;
  }

  public function getAuthor() {
    return $this->formatUser($this->authorName, $this->authorEmail);
  }

  public function getCommitter() {
    return $this->formatUser($this->committerName, $this->committerEmail);
  }

  public function getSummary() {
    return PhabricatorRepositoryCommitData::summarizeCommitMessage(
      $this->getMessage());
  }

  private function formatUser($name, $email) {
    if (strlen($name) && strlen($email)) {
      return "{$name} <{$email}>";
    } else if (strlen($email)) {
      return $email;
    } else if (strlen($name)) {
      return $name;
    } else {
      return null;
    }
  }

}

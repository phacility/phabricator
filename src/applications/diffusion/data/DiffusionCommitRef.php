<?php

final class DiffusionCommitRef extends Phobject {

  private $message;
  private $authorEpoch;
  private $authorName;
  private $authorEmail;
  private $committerName;
  private $committerEmail;
  private $hashes = array();

  public function newDictionary() {
    $hashes = $this->getHashes();
    $hashes = mpull($hashes, 'newDictionary');
    $hashes = array_values($hashes);

    return array(
      'authorEpoch' => $this->authorEpoch,
      'authorName' => $this->authorName,
      'authorEmail' => $this->authorEmail,
      'committerName' => $this->committerName,
      'committerEmail' => $this->committerEmail,
      'message' => $this->message,
      'hashes' => $hashes,
    );
  }

  public static function newFromDictionary(array $map) {
    $hashes = idx($map, 'hashes', array());
    foreach ($hashes as $key => $hash_map) {
      $hashes[$key] = DiffusionCommitHash::newFromDictionary($hash_map);
    }
    $hashes = array_values($hashes);

    $author_epoch = idx($map, 'authorEpoch');
    $author_name = idx($map, 'authorName');
    $author_email = idx($map, 'authorEmail');
    $committer_name = idx($map, 'committerName');
    $committer_email = idx($map, 'committerEmail');
    $message = idx($map, 'message');

    return id(new self())
      ->setAuthorEpoch($author_epoch)
      ->setAuthorName($author_name)
      ->setAuthorEmail($author_email)
      ->setCommitterName($committer_name)
      ->setCommitterEmail($committer_email)
      ->setMessage($message)
      ->setHashes($hashes);
  }

  public function setHashes(array $hashes) {
    assert_instances_of($hashes, 'DiffusionCommitHash');
    $this->hashes = $hashes;
    return $this;
  }

  public function getHashes() {
    return $this->hashes;
  }

  public function setAuthorEpoch($author_epoch) {
    $this->authorEpoch = $author_epoch;
    return $this;
  }

  public function getAuthorEpoch() {
    return $this->authorEpoch;
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
    if ($name === null) {
      $name = '';
    }
    if ($email === null) {
      $email = '';
    }

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

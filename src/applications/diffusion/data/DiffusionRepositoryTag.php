<?php

final class DiffusionRepositoryTag extends Phobject {

  private $author;
  private $epoch;
  private $commitIdentifier;
  private $name;
  private $description;
  private $type;

  private $message = false;

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

  public function attachMessage($message) {
    $this->message = $message;
    return $this;
  }

  public function getMessage() {
    if ($this->message === false) {
      throw new Exception(pht('Message is not attached!'));
    }
    return $this->message;
  }

  public function toDictionary() {
    $dict = array(
      'author' => $this->getAuthor(),
      'epoch' => $this->getEpoch(),
      'commitIdentifier' => $this->getCommitIdentifier(),
      'name' => $this->getName(),
      'description' => $this->getDescription(),
      'type' => $this->getType(),
    );

    if ($this->message !== false) {
      $dict['message'] = $this->message;
    }

    return $dict;
  }

  public static function newFromConduit(array $dicts) {
    $tags = array();
    foreach ($dicts as $dict) {
      $tag = id(new DiffusionRepositoryTag())
        ->setAuthor($dict['author'])
        ->setEpoch($dict['epoch'])
        ->setCommitIdentifier($dict['commitIdentifier'])
        ->setName($dict['name'])
        ->setDescription($dict['description'])
        ->setType($dict['type']);

      if (array_key_exists('message', $dict)) {
        $tag->attachMessage($dict['message']);
      }

      $tags[] = $tag;
    }
    return $tags;
  }

}

<?php

/**
 * Represents something which can be the target of messages, like a user or
 * channel.
 */
abstract class PhabricatorBotTarget extends Phobject {

  private $name;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  abstract public function isPublic();

}

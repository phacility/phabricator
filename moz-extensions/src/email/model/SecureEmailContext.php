<?php


class SecureEmailContext {
  public string $eventKind;
  /** @deprecated needed until client implements Bug 1667309 */
  public string $actorName;
  public Actor $actor;
  public SecureEmailRevision $revision;
  public SecureEmailBody $body;

  public function __construct(string $eventKind, Actor $actor, SecureEmailRevision $revision, SecureEmailBody $body) {
    $this->eventKind = $eventKind;
    $this->actorName = $actor->userName;
    $this->actor = $actor;
    $this->revision = $revision;
    $this->body = $body;
  }


}
<?php


class PublicEmailContext {
  public string $eventKind;
  /** @deprecated needed until client implements Bug 1667309 */
  public string $actorName;
  public Actor $actor;
  public EmailRevision $revision;
  public PublicEmailBody $body;

  public function __construct(string $eventKind, Actor $actor, EmailRevision $revision, PublicEmailBody $body) {
    $this->eventKind = $eventKind;
    $this->actorName = $actor->userName;
    $this->actor = $actor;
    $this->revision = $revision;
    $this->body = $body;
  }


}
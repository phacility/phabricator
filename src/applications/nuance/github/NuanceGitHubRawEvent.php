<?php

final class NuanceGitHubRawEvent extends Phobject {

  private $raw;
  private $type;

  const TYPE_ISSUE = 'issue';
  const TYPE_REPOSITORY = 'repository';

  public static function newEvent($type, array $raw) {
    $event = new self();
    $event->type = $type;
    $event->raw = $raw;
    return $event;
  }

  public function getRepositoryFullName() {
    return $this->getRepositoryFullRawName();
  }

  public function isIssueEvent() {
    if ($this->isPullRequestEvent()) {
      return false;
    }

    if ($this->type == self::TYPE_ISSUE) {
      return true;
    }

    switch ($this->getIssueRawKind()) {
      case 'IssuesEvent':
      case 'IssuesCommentEvent':
        return true;
    }

    return false;
  }

  public function isPullRequestEvent() {
    return false;
  }

  public function getIssueNumber() {
    if (!$this->isIssueEvent()) {
      return null;
    }

    $raw = $this->raw;

    if ($this->type == self::TYPE_ISSUE) {
      return idxv($raw, array('issue', 'number'));
    }

    if ($this->type == self::TYPE_REPOSITORY) {
      return idxv($raw, array('payload', 'issue', 'number'));
    }

    return null;
  }

  private function getRepositoryFullRawName() {
    $raw = $this->raw;
    return idxv($raw, array('repo', 'name'));
  }

  private function getIssueRawKind() {
    $raw = $this->raw;
    return idxv($raw, array('type'));
  }

}

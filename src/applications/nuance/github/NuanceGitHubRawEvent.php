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
        return true;
      case 'IssueCommentEvent':
        if (!$this->getRawPullRequestData()) {
          return true;
        }
        break;
    }

    return false;
  }

  public function isPullRequestEvent() {
    if ($this->type == self::TYPE_ISSUE) {
      // TODO: This is wrong, some of these are pull events.
      return false;
    }

    $raw = $this->raw;

    switch ($this->getIssueRawKind()) {
      case 'PullRequestEvent':
        return true;
      case 'IssueCommentEvent':
        if ($this->getRawPullRequestData()) {
          return true;
        }
        break;
    }

    return false;
  }

  public function getIssueNumber() {
    if (!$this->isIssueEvent()) {
      return null;
    }

    return $this->getRawIssueNumber();
  }

  public function getPullRequestNumber() {
    if (!$this->isPullRequestEvent()) {
      return null;
    }

    return $this->getRawIssueNumber();
  }


  public function getID() {
    $raw = $this->raw;

    $id = idx($raw, 'id');
    if ($id) {
      return (int)$id;
    }

    return null;
  }

  public function getURI() {
    $raw = $this->raw;

    if ($this->isIssueEvent()) {
      if ($this->type == self::TYPE_ISSUE) {
        $uri = idxv($raw, array('issue', 'html_url'));
        $uri = $uri.'#event-'.$this->getID();
      } else {
        $uri = idxv($raw, array('payload', 'issue', 'html_url'));
        $uri = $uri.'#event-'.$this->getID();
      }
    } else if ($this->isPullRequestEvent()) {
      if ($this->type == self::TYPE_ISSUE) {
        $uri = idxv($raw, array('issue', 'html_url'));
        $uri = $uri.'#event-'.$this->getID();
      } else {
        // The format of pull request events varies so we need to fish around
        // a bit to find the correct URI.
        $uri = idxv($raw, array('payload', 'pull_request', 'html_url'));
        if (!$uri) {
          $uri = idxv($raw, array('payload', 'issue', 'html_url'));
        }
        $uri = $uri.'#event-'.$this->getID();
      }
    } else {
      switch ($this->getIssueRawKind()) {
        case 'PushEvent':
          // These don't really have a URI since there may be multiple commits
          // involved and GitHub doesn't bundle the push as an object on its
          // own. Just try to find the URI for the log. The API also does
          // not return any HTML URI for these events.

          $head = idxv($raw, array('payload', 'head'));
          if ($head === null) {
            return null;
          }

          $repo = $this->getRepositoryFullRawName();
          return "https://github.com/{$repo}/commits/{$head}";
        case 'WatchEvent':
          // These have no reasonable URI.
          return null;
        default:
          return null;
      }
    }

    return $uri;
  }

  private function getRepositoryFullRawName() {
    $raw = $this->raw;

    $full = idxv($raw, array('repo', 'name'));
    if (strlen($full)) {
      return $full;
    }

    // For issue events, the repository is not identified explicitly in the
    // response body. Parse it out of the URI.

    $matches = null;
    $ok = preg_match(
      '(/repos/((?:[^/]+)/(?:[^/]+))/issues/events/)',
      idx($raw, 'url'),
      $matches);

    if ($ok) {
      return $matches[1];
    }

    return null;
  }

  private function getIssueRawKind() {
    $raw = $this->raw;
    return idxv($raw, array('type'));
  }

  private function getRawIssueNumber() {
    $raw = $this->raw;

    if ($this->type == self::TYPE_ISSUE) {
      return idxv($raw, array('issue', 'number'));
    }

    if ($this->type == self::TYPE_REPOSITORY) {
      $issue_number = idxv($raw, array('payload', 'issue', 'number'));
      if ($issue_number) {
        return $issue_number;
      }

      $pull_number = idxv($raw, array('payload', 'number'));
      if ($pull_number) {
        return $pull_number;
      }
    }

    return null;
  }

  private function getRawPullRequestData() {
    $raw = $this->raw;
    return idxv($raw, array('payload', 'issue', 'pull_request'));
  }

}

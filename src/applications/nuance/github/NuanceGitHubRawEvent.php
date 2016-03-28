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

  public function getComment() {
    if (!$this->isIssueEvent() && !$this->isPullRequestEvent()) {
      return null;
    }

    $raw = $this->raw;

    return idxv($raw, array('payload', 'comment', 'body'));
  }

  public function getURI() {
    $raw = $this->raw;

    if ($this->isIssueEvent() || $this->isPullRequestEvent()) {
      if ($this->type == self::TYPE_ISSUE) {
        $uri = idxv($raw, array('issue', 'html_url'));
        $uri = $uri.'#event-'.$this->getID();
      } else {
        // The format of pull request events varies so we need to fish around
        // a bit to find the correct URI.
        $uri = idxv($raw, array('payload', 'pull_request', 'html_url'));
        $need_anchor = true;

        // For comments, we get a different anchor to link to the comment. In
        // this case, the URI comes with an anchor already.
        if (!$uri) {
          $uri = idxv($raw, array('payload', 'comment', 'html_url'));
          $need_anchor = false;
        }

        if (!$uri) {
          $uri = idxv($raw, array('payload', 'issue', 'html_url'));
          $need_anchor = true;
        }

        if ($need_anchor) {
          $uri = $uri.'#event-'.$this->getID();
        }
      }
    } else {
      switch ($this->getIssueRawKind()) {
        case 'CreateEvent':
          $ref = idxv($raw, array('payload', 'ref'));

          $repo = $this->getRepositoryFullRawName();
          return "https://github.com/{$repo}/commits/{$ref}";
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

  public function getEventFullTitle() {
    switch ($this->type) {
      case self::TYPE_ISSUE:
        $title = $this->getRawIssueEventTitle();
        break;
      case self::TYPE_REPOSITORY:
        $title = $this->getRawRepositoryEventTitle();
        break;
      default:
        $title = pht('Unknown Event Type ("%s")', $this->type);
        break;
    }

    return pht(
      'GitHub %s %s (%s)',
      $this->getRepositoryFullRawName(),
      $this->getTargetObjectName(),
      $title);
  }

  public function getActorGitHubUserID() {
    $raw = $this->raw;
    return (int)idxv($raw, array('actor', 'id'));
  }

  private function getTargetObjectName() {
    if ($this->isPullRequestEvent()) {
      $number = $this->getRawIssueNumber();
      return pht('Pull Request #%d', $number);
    } else if ($this->isIssueEvent()) {
      $number = $this->getRawIssueNumber();
      return pht('Issue #%d', $number);
    } else if ($this->type == self::TYPE_REPOSITORY) {
      $raw = $this->raw;


      $type = idx($raw, 'type');
      switch ($type) {
        case 'CreateEvent':
          $ref = idxv($raw, array('payload', 'ref'));
          $ref_type = idxv($raw, array('payload', 'ref_type'));

          switch ($ref_type) {
            case 'branch':
              return pht('Branch %s', $ref);
            case 'tag':
              return pht('Tag %s', $ref);
            default:
              return pht('Ref %s', $ref);
          }
          break;
        case 'PushEvent':
          $ref = idxv($raw, array('payload', 'ref'));
          if (preg_match('(^refs/heads/)', $ref)) {
            return pht('Branch %s', substr($ref, strlen('refs/heads/')));
          } else {
            return pht('Ref %s', $ref);
          }
          break;
        case 'WatchEvent':
          $actor = idxv($raw, array('actor', 'login'));
          return pht('User %s', $actor);
      }

      return pht('Unknown Object');
    } else {
      return pht('Unknown Object');
    }
  }

  private function getRawIssueEventTitle() {
    $raw = $this->raw;

    $action = idxv($raw, array('event'));
    switch ($action) {
      case 'assigned':
        $assignee = idxv($raw, array('assignee', 'login'));
        $title = pht('Assigned: %s', $assignee);
        break;
      case 'closed':
        $title = pht('Closed');
        break;
      case 'demilestoned':
        $milestone = idxv($raw, array('milestone', 'title'));
        $title = pht('Removed Milestone: %s', $milestone);
        break;
      case 'labeled':
        $label = idxv($raw, array('label', 'name'));
        $title = pht('Added Label: %s', $label);
        break;
      case 'locked':
        $title = pht('Locked');
        break;
      case 'milestoned':
        $milestone = idxv($raw, array('milestone', 'title'));
        $title = pht('Added Milestone: %s', $milestone);
        break;
      case 'renamed':
        $title = pht('Renamed');
        break;
      case 'reopened':
        $title = pht('Reopened');
        break;
      case 'unassigned':
        $assignee = idxv($raw, array('assignee', 'login'));
        $title = pht('Unassigned: %s', $assignee);
        break;
      case 'unlabeled':
        $label = idxv($raw, array('label', 'name'));
        $title = pht('Removed Label: %s', $label);
        break;
      case 'unlocked':
        $title = pht('Unlocked');
        break;
      default:
        $title = pht('"%s"', $action);
        break;
    }


    return $title;
  }

  private function getRawRepositoryEventTitle() {
    $raw = $this->raw;

    $type = idx($raw, 'type');
    switch ($type) {
      case 'CreateEvent':
        return pht('Created');
      case 'PushEvent':
        $head = idxv($raw, array('payload', 'head'));
        $head = substr($head, 0, 12);
        return pht('Pushed: %s', $head);
      case 'IssuesEvent':
        $action = idxv($raw, array('payload', 'action'));
        switch ($action) {
          case 'closed':
            return pht('Closed');
          case 'opened':
            return pht('Created');
          case 'reopened':
            return pht('Reopened');
          default:
            return pht('"%s"', $action);
        }
        break;
      case 'IssueCommentEvent':
        $action = idxv($raw, array('payload', 'action'));
        switch ($action) {
          case 'created':
            return pht('Comment');
          default:
            return pht('"%s"', $action);
        }
        break;
      case 'PullRequestEvent':
        $action = idxv($raw, array('payload', 'action'));
        switch ($action) {
          case 'opened':
            return pht('Created');
          default:
            return pht('"%s"', $action);
        }
        break;
      case 'WatchEvent':
        return pht('Watched');
    }

    return pht('"%s"', $type);
  }

}

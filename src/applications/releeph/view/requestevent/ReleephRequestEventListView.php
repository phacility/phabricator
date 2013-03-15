<?php

final class ReleephRequestEventListView extends AphrontView {

  private $events;
  private $handles;

  public function setEvents(array $events) {
    assert_instances_of($events, 'ReleephRequestEvent');
    $this->events = $events;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function render() {
    $views = array();

    $discovered_commits = array();
    foreach ($this->events as $event) {
      $commit_id = $event->getDetail('newCommitIdentifier');
      switch ($event->getType()) {
        case ReleephRequestEvent::TYPE_DISCOVERY:
          $discovered_commits[$commit_id] = true;
          break;
      }
    }

    $markup_engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();
    $markup_engine->setConfig('viewer', $this->getUser());

    foreach ($this->events as $event) {
      $description = $this->describeEvent($event);
      if (!$description) {
        continue;
      }

      if ($event->getType() === ReleephRequestEvent::TYPE_COMMIT) {
        $commit_id = $event->getDetail('newCommitIdentifier');
        if (idx($discovered_commits, $commit_id)) {
          continue;
        }
      }

      $actor_handle = $this->handles[$event->getActorPHID()];
      $description = $this->describeEvent($event);
      $action = phutil_tag(
        'div',
        array(),
        array(
          $actor_handle->renderLink(),
          ' ',
          $description));

      $view = id(new PhabricatorTransactionView())
        ->setUser($this->user)
        ->setImageURI($actor_handle->getImageURI())
        ->setEpoch($event->getDateCreated())
        ->setActions(array($action))
        ->addClass($this->getTransactionClass($event));

      $comment = $this->getEventComment($event);
      if ($comment) {
        $markup = phutil_tag(
          'div',
          array(
            'class' => 'phabricator-remarkup',
          ),
          phutil_safe_html(
            $markup_engine->markupText($comment)));
        $view->appendChild($markup);
      }

      $views[] = $view;
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'releeph-request-event-list',
      ),
      $views);
  }

  public function renderForEmail() {
    $items = array();
    foreach ($this->events as $event) {
      $description = $this->describeEvent($event);
      if (!$description) {
        continue;
      }
      $actor = $this->handles[$event->getActorPHID()]->getName();
      $items[] = $actor.' '.$description;

      $comment = $this->getEventComment($event);
      if ($comment) {
        $items[] = preg_replace('/^/m', '  ', $comment);
      }
    }

    return implode("\n\n", $items);
  }

  private function describeEvent(ReleephRequestEvent $event) {
    $type = $event->getType();

    switch ($type) {
      case ReleephRequestEvent::TYPE_CREATE:
        return "created this request.";
        break;

      case ReleephRequestEvent::TYPE_STATUS:
        $status = $event->getStatusAfter();
        return sprintf(
          "updated status to %s.",
          ReleephRequest::getStatusDescriptionFor($status));
        break;

      case ReleephRequestEvent::TYPE_USER_INTENT:
        $intent = $event->getDetail('newIntent');
        $was_pusher = $event->getDetail('wasPusher');
        if ($intent == ReleephRequest::INTENT_WANT) {
          if ($was_pusher) {
            $verb = "approved";
          } else {
            $verb = "wanted";
          }
        } else {
          if ($was_pusher) {
            $verb = "rejected";
          } else {
            $verb = "passed on";
          }
        }
        return "{$verb} this request.";
        break;

      case ReleephRequestEvent::TYPE_PICK_STATUS:
        $pick_status = $event->getDetail('newPickStatus');
        switch ($pick_status) {
          case ReleephRequest::PICK_FAILED:
            return "found a conflict when picking.";
            break;

          case ReleephRequest::REVERT_FAILED:
            return "found a conflict when reverting.";
            break;

          case ReleephRequest::PICK_OK:
          case ReleephRequest::REVERT_OK:
            // (nothing)
            break;

          default:
            return "changed pick-status to {$pick_status}.";
            break;
        }
        break;

      case ReleephRequestEvent::TYPE_MANUAL_ACTION:
        $action = $event->getDetail('action');
        return "claimed to have manually {$action}ed this request.";
        break;

      case ReleephRequestEvent::TYPE_COMMIT:
        $action = $event->getDetail('action');
        if ($action) {
          return "{$action}ed this request.";
        } else {
          return "did something with this request.";
        }
        break;

      case ReleephRequestEvent::TYPE_DISCOVERY:
        $action = $event->getDetail('action');
        if ($action) {
          return "{$action}ed this request.";
        } else {
          // It's unlikely we'll have action-less TYPE_DISCOVERY events, but I
          // used this during testing and I guess it's a useful safety net.
          return "discovered this request in the branch.";
        }
        break;

      case ReleephRequestEvent::TYPE_COMMENT:
        return "commented on this request.";
        break;

      default:
        return "did event of type {$type}.";
        break;
    }
  }

  private function getEventComment(ReleephRequestEvent $event) {
    switch ($event->getType()) {
      case ReleephRequestEvent::TYPE_CREATE:
        $commit_phid = $event->getDetail('commitPHID');
        return sprintf(
          "Commit %s was requested.",
          $this->handles[$commit_phid]->getName());
        break;

      case ReleephRequestEvent::TYPE_STATUS:
      case ReleephRequestEvent::TYPE_USER_INTENT:
      case ReleephRequestEvent::TYPE_PICK_STATUS:
      case ReleephRequestEvent::TYPE_MANUAL_ACTION:
        // no comment!
        break;

      case ReleephRequestEvent::TYPE_COMMIT:
        return sprintf(
          "Closed by commit %s.",
          $event->getDetail('newCommitIdentifier'));
        break;

      case ReleephRequestEvent::TYPE_DISCOVERY:
        $author_phid = $event->getDetail('authorPHID');
        $commit_phid = $event->getDetail('newCommitPHID');
        if ($author_phid && $author_phid != $event->getActorPHID()) {
          return sprintf(
            "Closed by commit %s (with author set to @%s).",
            $this->handles[$commit_phid]->getName(),
            $this->handles[$author_phid]->getName());
        } else {
          return sprintf(
            'Closed by commit %s.',
            $this->handles[$commit_phid]->getName());
        }
        break;

      case ReleephRequestEvent::TYPE_COMMENT:
        return $event->getComment();
        break;
    }
  }

  private function getTransactionClass($event) {
    switch ($event->getType()) {
      case ReleephRequestEvent::TYPE_COMMIT:
      case ReleephRequestEvent::TYPE_DISCOVERY:
        $action = $event->getDetail('action');
        if ($action == 'pick') {
          return 'releeph-border-color-picked';
        } else {
          return 'releeph-border-color-abandoned';
        }
        break;

      case ReleephRequestEvent::TYPE_COMMENT:
        return 'releeph-border-color-comment';
        break;

      default:
        $status_after = $event->getStatusAfter();
        $class_suffix = ReleephRequest::getStatusClassSuffixFor($status_after);
        return ' releeph-border-color-'.$class_suffix;
        break;
    }
  }

}

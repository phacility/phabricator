<?php

/**
 * @concrete-extensible
 */
class PhabricatorApplicationTransactionView extends AphrontView {

  private $transactions;
  private $engine;
  private $anchorOffset = 1;
  private $showEditActions = true;
  private $isPreview;
  private $objectPHID;
  private $shouldTerminate = false;
  private $quoteTargetID;
  private $quoteRef;

  public function setQuoteRef($quote_ref) {
    $this->quoteRef = $quote_ref;
    return $this;
  }

  public function getQuoteRef() {
    return $this->quoteRef;
  }

  public function setQuoteTargetID($quote_target_id) {
    $this->quoteTargetID = $quote_target_id;
    return $this;
  }

  public function getQuoteTargetID() {
    return $this->quoteTargetID;
  }

  public function setObjectPHID($object_phid) {
    $this->objectPHID = $object_phid;
    return $this;
  }

  public function getObjectPHID() {
    return $this->objectPHID;
  }

  public function setIsPreview($is_preview) {
    $this->isPreview = $is_preview;
    return $this;
  }

  public function setShowEditActions($show_edit_actions) {
    $this->showEditActions = $show_edit_actions;
    return $this;
  }

  public function getShowEditActions() {
    return $this->showEditActions;
  }

  public function setAnchorOffset($anchor_offset) {
    $this->anchorOffset = $anchor_offset;
    return $this;
  }

  public function setMarkupEngine(PhabricatorMarkupEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  public function setTransactions(array $transactions) {
    assert_instances_of($transactions, 'PhabricatorApplicationTransaction');
    $this->transactions = $transactions;
    return $this;
  }

  public function setShouldTerminate($term) {
    $this->shouldTerminate = $term;
    return $this;
  }

  public function buildEvents($with_hiding = false) {
    $user = $this->getUser();

    $anchor = $this->anchorOffset;

    $xactions = $this->transactions;

    $xactions = $this->filterHiddenTransactions($xactions);
    $xactions = $this->groupRelatedTransactions($xactions);
    $groups = $this->groupDisplayTransactions($xactions);

    // If the viewer has interacted with this object, we hide things from
    // before their most recent interaction by default. This tends to make
    // very long threads much more manageable, because you don't have to
    // scroll through a lot of history and can focus on just new stuff.

    $show_group = null;

    if ($with_hiding) {
      // Find the most recent comment by the viewer.
      $group_keys = array_keys($groups);
      $group_keys = array_reverse($group_keys);

      // If we would only hide a small number of transactions, don't hide
      // anything. Just don't examine the last few keys. Also, we always
      // want to show the most recent pieces of activity, so don't examine
      // the first few keys either.
      $group_keys = array_slice($group_keys, 2, -2);

      $type_comment = PhabricatorTransactions::TYPE_COMMENT;
      foreach ($group_keys as $group_key) {
        $group = $groups[$group_key];
        foreach ($group as $xaction) {
          if ($xaction->getAuthorPHID() == $user->getPHID() &&
              $xaction->getTransactionType() == $type_comment) {
            // This is the most recent group where the user commented.
            $show_group = $group_key;
            break 2;
          }
        }
      }
    }

    $events = array();
    $hide_by_default = ($show_group !== null);

    foreach ($groups as $group_key => $group) {
      if ($hide_by_default && ($show_group === $group_key)) {
        $hide_by_default = false;
      }

      $group_event = null;
      foreach ($group as $xaction) {
        $event = $this->renderEvent($xaction, $group, $anchor);
        $event->setHideByDefault($hide_by_default);
        $anchor++;
        if (!$group_event) {
          $group_event = $event;
        } else {
          $group_event->addEventToGroup($event);
        }
      }
      $events[] = $group_event;

    }

    return $events;
  }

  public function render() {
    if (!$this->getObjectPHID()) {
      throw new Exception('Call setObjectPHID() before render()!');
    }

    $view = new PHUITimelineView();
    $view->setShouldTerminate($this->shouldTerminate);
    $events = $this->buildEvents($with_hiding = true);
    foreach ($events as $event) {
      $view->addEvent($event);
    }

    if ($this->getShowEditActions()) {
      $list_id = celerity_generate_unique_node_id();

      $view->setID($list_id);

      Javelin::initBehavior(
        'phabricator-transaction-list',
        array(
          'listID'          => $list_id,
          'objectPHID'      => $this->getObjectPHID(),
          'nextAnchor'      => $this->anchorOffset + count($events),
        ));
    }

    return $view->render();
  }

  protected function getOrBuildEngine() {
    if (!$this->engine) {
      $field = PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT;

      $engine = id(new PhabricatorMarkupEngine())
        ->setViewer($this->getUser());
      foreach ($this->transactions as $xaction) {
        if (!$xaction->hasComment()) {
          continue;
        }
        $engine->addObject($xaction->getComment(), $field);
      }
      $engine->process();

      $this->engine = $engine;
    }

    return $this->engine;
  }

  private function buildChangeDetailsLink(
    PhabricatorApplicationTransaction $xaction) {

    return javelin_tag(
      'a',
      array(
        'href' => '/transactions/detail/'.$xaction->getPHID().'/',
        'sigil' => 'workflow',
      ),
      pht('(Show Details)'));
  }

  private function buildExtraInformationLink(
    PhabricatorApplicationTransaction $xaction) {

    $link = $xaction->renderExtraInformationLink();
    if (!$link) {
      return null;
    }

    return phutil_tag(
      'span',
      array(
        'class' => 'phui-timeline-extra-information',
      ),
      array(" \xC2\xB7  ", $link));
  }

  protected function shouldGroupTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {
    return false;
  }

  protected function renderTransactionContent(
    PhabricatorApplicationTransaction $xaction) {

    $field = PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT;
    $engine = $this->getOrBuildEngine();
    $comment = $xaction->getComment();

    if ($comment) {
      if ($comment->getIsRemoved()) {
        return javelin_tag(
          'span',
          array(
            'class' => 'comment-deleted',
            'sigil' => 'transaction-comment',
            'meta'  => array('phid' => $comment->getTransactionPHID()),
          ),
          pht(
            'This comment was removed by %s.',
            $xaction->getHandle($comment->getAuthorPHID())->renderLink()));
      } else if ($comment->getIsDeleted()) {
        return javelin_tag(
          'span',
          array(
            'class' => 'comment-deleted',
            'sigil' => 'transaction-comment',
            'meta'  => array('phid' => $comment->getTransactionPHID()),
          ),
          pht('This comment has been deleted.'));
      } else if ($xaction->hasComment()) {
        return javelin_tag(
          'span',
          array(
            'class' => 'transaction-comment',
            'sigil' => 'transaction-comment',
            'meta'  => array('phid' => $comment->getTransactionPHID()),
          ),
          $engine->getOutput($comment, $field));
      } else {
        // This is an empty, non-deleted comment. Usually this happens when
        // rendering previews.
        return null;
      }
    }

    return null;
  }

  private function filterHiddenTransactions(array $xactions) {
    foreach ($xactions as $key => $xaction) {
      if ($xaction->shouldHide()) {
        unset($xactions[$key]);
      }
    }
    return $xactions;
  }

  private function groupRelatedTransactions(array $xactions) {
    $last = null;
    $last_key = null;
    $groups = array();
    foreach ($xactions as $key => $xaction) {
      if ($last && $this->shouldGroupTransactions($last, $xaction)) {
        $groups[$last_key][] = $xaction;
        unset($xactions[$key]);
      } else {
        $last = $xaction;
        $last_key = $key;
      }
    }

    foreach ($xactions as $key => $xaction) {
      $xaction->attachTransactionGroup(idx($groups, $key, array()));
    }

    return $xactions;
  }

  private function groupDisplayTransactions(array $xactions) {
    $groups = array();
    $group = array();
    foreach ($xactions as $xaction) {
      if ($xaction->shouldDisplayGroupWith($group)) {
        $group[] = $xaction;
      } else {
        if ($group) {
          $groups[] = $group;
        }
        $group = array($xaction);
      }
    }

    if ($group) {
      $groups[] = $group;
    }

    foreach ($groups as $key => $group) {
      $group = msort($group, 'getActionStrength');
      $group = array_reverse($group);
      $groups[$key] = $group;
    }

    return $groups;
  }

  private function renderEvent(
    PhabricatorApplicationTransaction $xaction,
    array $group,
    $anchor) {
    $viewer = $this->getUser();

    $event = id(new PHUITimelineEventView())
      ->setUser($viewer)
      ->setTransactionPHID($xaction->getPHID())
      ->setUserHandle($xaction->getHandle($xaction->getAuthorPHID()))
      ->setIcon($xaction->getIcon())
      ->setColor($xaction->getColor());

    list($token, $token_removed) = $xaction->getToken();
    if ($token) {
      $event->setToken($token, $token_removed);
    }

    if (!$this->shouldSuppressTitle($xaction, $group)) {
      $title = $xaction->getTitle();
      if ($xaction->hasChangeDetails()) {
        if (!$this->isPreview) {
          $details = $this->buildChangeDetailsLink($xaction);
          $title = array(
            $title,
            ' ',
            $details,
          );
        }
      }

      if (!$this->isPreview) {
        $more = $this->buildExtraInformationLink($xaction);
        if ($more) {
          $title = array($title, ' ', $more);
        }
      }

      $event->setTitle($title);
    }

    if ($this->isPreview) {
      $event->setIsPreview(true);
    } else {
      $event
        ->setDateCreated($xaction->getDateCreated())
        ->setContentSource($xaction->getContentSource())
        ->setAnchor($anchor);
    }

    $transaction_type = $xaction->getTransactionType();
    $comment_type = PhabricatorTransactions::TYPE_COMMENT;
    $is_normal_comment = ($transaction_type == $comment_type);

    if ($this->getShowEditActions() &&
        !$this->isPreview &&
        $is_normal_comment) {

      $has_deleted_comment =
        $xaction->getComment() &&
        $xaction->getComment()->getIsDeleted();

      $has_removed_comment =
        $xaction->getComment() &&
        $xaction->getComment()->getIsRemoved();

      if ($xaction->getCommentVersion() > 1 && !$has_removed_comment) {
        $event->setIsEdited(true);
      }

      // If we have a place for quoted text to go and this is a quotable
      // comment, pass the quote target ID to the event view.
      if ($this->getQuoteTargetID()) {
        if ($xaction->hasComment()) {
          if (!$has_removed_comment && !$has_deleted_comment) {
            $event->setQuoteTargetID($this->getQuoteTargetID());
            $event->setQuoteRef($this->getQuoteRef());
          }
        }
      }

      $can_edit = PhabricatorPolicyCapability::CAN_EDIT;

      if ($xaction->hasComment() || $has_deleted_comment) {
        $has_edit_capability = PhabricatorPolicyFilter::hasCapability(
          $viewer,
          $xaction,
          $can_edit);
        if ($has_edit_capability && !$has_removed_comment) {
          $event->setIsEditable(true);
        }
        if ($has_edit_capability || $viewer->getIsAdmin()) {
          if (!$has_removed_comment) {
            $event->setIsRemovable(true);
          }
        }
      }
    }

    $comment = $this->renderTransactionContent($xaction);
    if ($comment) {
      $event->appendChild($comment);
    }

    return $event;
  }

  private function shouldSuppressTitle(
    PhabricatorApplicationTransaction $xaction,
    array $group) {

    // This is a little hard-coded, but we don't have any other reasonable
    // cases for now. Suppress "commented on" if there are other actions in
    // the display group.

    if (count($group) > 1) {
      $type_comment = PhabricatorTransactions::TYPE_COMMENT;
      if ($xaction->getTransactionType() == $type_comment) {
        return true;
      }
    }

    return false;
  }

}

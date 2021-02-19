<?php

/**
 * @concrete-extensible
 */
class PhabricatorApplicationTransactionView extends AphrontView {

  private $transactions;
  private $engine;
  private $showEditActions = true;
  private $isPreview;
  private $object;
  private $objectPHID;
  private $shouldTerminate = false;
  private $quoteTargetID;
  private $quoteRef;
  private $pager;
  private $renderAsFeed;
  private $hideCommentOptions = false;
  private $viewData = array();

  public function setRenderAsFeed($feed) {
    $this->renderAsFeed = $feed;
    return $this;
  }

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

  public function setObject(
    PhabricatorApplicationTransactionInterface $object) {
    $this->object = $object;
    return $this;
  }

  private function getObject() {
    return $this->object;
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

  public function getIsPreview() {
    return $this->isPreview;
  }

  public function setShowEditActions($show_edit_actions) {
    $this->showEditActions = $show_edit_actions;
    return $this;
  }

  public function getShowEditActions() {
    return $this->showEditActions;
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

  public function getTransactions() {
    return $this->transactions;
  }

  public function setShouldTerminate($term) {
    $this->shouldTerminate = $term;
    return $this;
  }

  public function setPager(AphrontCursorPagerView $pager) {
    $this->pager = $pager;
    return $this;
  }

  public function getPager() {
    return $this->pager;
  }

  public function setHideCommentOptions($hide_comment_options) {
    $this->hideCommentOptions = $hide_comment_options;
    return $this;
  }

  public function getHideCommentOptions() {
    return $this->hideCommentOptions;
  }

  public function setViewData(array $view_data) {
    $this->viewData = $view_data;
    return $this;
  }

  public function getViewData() {
    return $this->viewData;
  }

  public function buildEvents($with_hiding = false) {
    $user = $this->getUser();

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
    $set_next_page_id = false;

    foreach ($groups as $group_key => $group) {
      if ($hide_by_default && ($show_group === $group_key)) {
        $hide_by_default = false;
        $set_next_page_id = true;
      }

      $group_event = null;
      foreach ($group as $xaction) {
        $event = $this->renderEvent($xaction, $group);
        $event->setHideByDefault($hide_by_default);
        if (!$group_event) {
          $group_event = $event;
        } else {
          $group_event->addEventToGroup($event);
        }
        if ($set_next_page_id) {
          $set_next_page_id = false;
          $pager = $this->getPager();
          if ($pager) {
            $pager->setNextPageID($xaction->getID());
          }
        }
      }
      $events[] = $group_event;

    }

    return $events;
  }

  public function render() {
    if (!$this->getObjectPHID()) {
      throw new PhutilInvalidStateException('setObjectPHID');
    }

    $view = $this->buildPHUITimelineView();

    if ($this->getShowEditActions()) {
      Javelin::initBehavior('phabricator-transaction-list');
    }

    return $view->render();
  }

  public function buildPHUITimelineView($with_hiding = true) {
    if (!$this->getObjectPHID()) {
      throw new PhutilInvalidStateException('setObjectPHID');
    }

    $view = id(new PHUITimelineView())
      ->setViewer($this->getViewer())
      ->setShouldTerminate($this->shouldTerminate)
      ->setQuoteTargetID($this->getQuoteTargetID())
      ->setQuoteRef($this->getQuoteRef())
      ->setViewData($this->getViewData());

    $events = $this->buildEvents($with_hiding);
    foreach ($events as $event) {
      $view->addEvent($event);
    }

    if ($this->getPager()) {
      $view->setPager($this->getPager());
    }

    return $view;
  }

  public function isTimelineEmpty() {
    return !count($this->buildEvents(true));
  }

  protected function getOrBuildEngine() {
    if (!$this->engine) {
      $field = PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT;

      $engine = id(new PhabricatorMarkupEngine())
        ->setViewer($this->getViewer());

      $object = $this->getObject();
      if ($object) {
        $engine->setContextObject($object);
      }

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
        'href' => $xaction->getChangeDetailsURI(),
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
      $results = array();

      // Sort transactions within the group by action strength, then by
      // chronological order. This makes sure that multiple actions of the
      // same type (like a close, then a reopen) render in the order they
      // were performed.
      $strength_groups = mgroup($group, 'getActionStrength');
      krsort($strength_groups);
      foreach ($strength_groups as $strength_group) {
        foreach (msort($strength_group, 'getID') as $xaction) {
          $results[] = $xaction;
        }
      }

      $groups[$key] = $results;
    }

    return $groups;
  }

  private function renderEvent(
    PhabricatorApplicationTransaction $xaction,
    array $group) {
    $viewer = $this->getViewer();

    $event = id(new PHUITimelineEventView())
      ->setViewer($viewer)
      ->setAuthorPHID($xaction->getAuthorPHID())
      ->setTransactionPHID($xaction->getPHID())
      ->setUserHandle($xaction->getHandle($xaction->getAuthorPHID()))
      ->setIcon($xaction->getIcon())
      ->setColor($xaction->getColor())
      ->setHideCommentOptions($this->getHideCommentOptions())
      ->setIsSilent($xaction->getIsSilentTransaction())
      ->setIsMFA($xaction->getIsMFATransaction())
      ->setIsLockOverride($xaction->getIsLockOverrideTransaction());

    list($token, $token_removed) = $xaction->getToken();
    if ($token) {
      $event->setToken($token, $token_removed);
    }

    if (!$this->shouldSuppressTitle($xaction, $group)) {
      if ($this->renderAsFeed) {
        $title = $xaction->getTitleForFeed();
      } else {
        $title = $xaction->getTitle();
      }
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
        ->setAnchor($xaction->getID());
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

      if (!$has_removed_comment) {
        $event->setIsNormalComment(true);
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

      $can_interact = PhabricatorPolicyFilter::canInteract(
        $viewer,
        $xaction->getObject());
      $event->setCanInteract($can_interact);
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

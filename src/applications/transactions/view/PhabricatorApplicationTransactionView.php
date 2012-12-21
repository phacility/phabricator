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

  public function buildEvents() {
    $field = PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT;
    $engine = $this->getOrBuildEngine();

    $user = $this->getUser();

    $anchor = $this->anchorOffset;
    $events = array();
    foreach ($this->transactions as $xaction) {
      if ($xaction->shouldHide()) {
        continue;
      }

      $event = id(new PhabricatorTimelineEventView())
        ->setUser($user)
        ->setTransactionPHID($xaction->getPHID())
        ->setUserHandle($xaction->getHandle($xaction->getAuthorPHID()))
        ->setIcon($xaction->getIcon())
        ->setColor($xaction->getColor())
        ->setTitle($xaction->getTitle());

      if ($this->isPreview) {
        $event->setIsPreview(true);
      } else {
        $event
          ->setDateCreated($xaction->getDateCreated())
          ->setContentSource($xaction->getContentSource())
          ->setAnchor($anchor);

        $anchor++;
      }


      $has_deleted_comment = $xaction->getComment() &&
        $xaction->getComment()->getIsDeleted();

      if ($this->getShowEditActions() && !$this->isPreview) {
        if ($xaction->getCommentVersion() > 1) {
          $event->setIsEdited(true);
        }

        $can_edit = PhabricatorPolicyCapability::CAN_EDIT;

        if ($xaction->hasComment() || $has_deleted_comment) {
          $has_edit_capability = PhabricatorPolicyFilter::hasCapability(
            $user,
            $xaction,
            $can_edit);
          if ($has_edit_capability) {
            $event->setIsEditable(true);
          }
        }
      }

      if ($xaction->hasComment()) {
        $event->appendChild(
          $engine->getOutput($xaction->getComment(), $field));
      } else if ($has_deleted_comment) {
        $event->appendChild(
          '<em>'.pht('This comment has been deleted.').'</em>');
      }

      $events[] = $event;
    }

    return $events;
  }

  public function render() {
    $view = new PhabricatorTimelineView();
    $events = $this->buildEvents();
    foreach ($events as $event) {
      $view->addEvent($event);
    }

    if ($this->getShowEditActions()) {
      $list_id = celerity_generate_unique_node_id();

      $view->setID($list_id);

      Javelin::initBehavior(
        'phabricator-transaction-list',
        array(
          'listID'      => $list_id,
          'nextAnchor'  => $this->anchorOffset + count($events),
        ));
    }

    return $view->render();
  }


  private function getOrBuildEngine() {
    if ($this->engine) {
      return $this->engine;
    }

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

    return $engine;
  }

}


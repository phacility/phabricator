<?php

/**
 * @concrete-extensible
 */
class PhabricatorApplicationTransactionView extends AphrontView {

  private $viewer;
  private $transactions;
  private $engine;
  private $anchorOffset = 0;
  private $showEditActions = true;

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

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function render() {
    $field = PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT;

    if (!$this->engine) {
      $engine = id(new PhabricatorMarkupEngine())
        ->setViewer($this->viewer);
      foreach ($this->transactions as $xaction) {
        if (!$xaction->hasComment()) {
          continue;
        }
        $engine->addObject($xaction->getComment(), $field);
      }
      $engine->process();

      $this->engine = $engine;
    }

    $view = new PhabricatorTimelineView();
    $viewer = $this->viewer;

    $anchor = $this->anchorOffset;
    foreach ($this->transactions as $xaction) {
      if ($xaction->shouldHide()) {
        continue;
      }

      $anchor++;
      $event = id(new PhabricatorTimelineEventView())
        ->setViewer($viewer)
        ->setTransactionPHID($xaction->getPHID())
        ->setUserHandle($xaction->getHandle($xaction->getAuthorPHID()))
        ->setIcon($xaction->getIcon())
        ->setColor($xaction->getColor())
        ->setTitle($xaction->getTitle())
        ->setDateCreated($xaction->getDateCreated())
        ->setContentSource($xaction->getContentSource())
        ->setAnchor($anchor);

      $has_deleted_comment = $xaction->getComment() &&
        $xaction->getComment()->getIsDeleted();

      if ($this->getShowEditActions()) {
        if ($xaction->getCommentVersion() > 1) {
          $event->setIsEdited(true);
        }

        $can_edit = PhabricatorPolicyCapability::CAN_EDIT;

        if ($xaction->hasComment() || $has_deleted_comment) {
          $has_edit_capability = PhabricatorPolicyFilter::hasCapability(
            $viewer,
            $xaction,
            $can_edit);
          if ($has_edit_capability) {
            $event->setIsEditable(true);
          }
        }
      }

      if ($xaction->hasComment()) {
        $event->appendChild(
          $this->engine->getOutput($xaction->getComment(), $field));
      } else if ($has_deleted_comment) {
        $event->appendChild(
          '<em>'.pht('This comment has been deleted.').'</em>');
      }

      $view->addEvent($event);
    }

    return $view->render();
  }
}


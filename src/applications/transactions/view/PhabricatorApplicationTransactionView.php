<?php

/**
 * @concrete-extensible
 */
class PhabricatorApplicationTransactionView extends AphrontView {

  private $viewer;
  private $transactions;
  private $engine;
  private $anchorOffset = 0;

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

    $anchor = $this->anchorOffset;
    foreach ($this->transactions as $xaction) {
      if ($xaction->shouldHide()) {
        continue;
      }

      $anchor++;
      $event = id(new PhabricatorTimelineEventView())
        ->setViewer($this->viewer)
        ->setUserHandle($xaction->getHandle($xaction->getAuthorPHID()))
        ->setIcon($xaction->getIcon())
        ->setColor($xaction->getColor())
        ->setTitle($xaction->getTitle())
        ->setDateCreated($xaction->getDateCreated())
        ->setContentSource($xaction->getContentSource())
        ->setAnchor($anchor);

      if ($xaction->hasComment()) {
        $event->appendChild(
          $this->engine->getOutput($xaction->getComment(), $field));
      }

      $view->addEvent($event);
    }

    return $view->render();
  }
}


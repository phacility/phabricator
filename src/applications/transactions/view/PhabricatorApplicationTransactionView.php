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
  private $isDetailView;

  public function setIsDetailView($is_detail_view) {
    $this->isDetailView = $is_detail_view;
    return $this;
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

  public function buildEvents() {
    $user = $this->getUser();

    $anchor = $this->anchorOffset;
    $events = array();

    $xactions = $this->transactions;
    foreach ($xactions as $key => $xaction) {
      if ($xaction->shouldHide()) {
        unset($xactions[$key]);
      }
    }

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

      $event = id(new PhabricatorTimelineEventView())
        ->setUser($user)
        ->setTransactionPHID($xaction->getPHID())
        ->setUserHandle($xaction->getHandle($xaction->getAuthorPHID()))
        ->setIcon($xaction->getIcon())
        ->setColor($xaction->getColor());

      $title = $xaction->getTitle();
      if ($xaction->hasChangeDetails()) {
        if ($this->isPreview || $this->isDetailView) {
          $details = $this->buildChangeDetails($xaction);
        } else {
          $details = $this->buildChangeDetailsLink($xaction);
        }
        $title = array(
          $title,
          ' ',
          $details,
        );
      }
      $event->setTitle($title);

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

      $content = $this->renderTransactionContent($xaction);
      if ($content) {
        $event->appendChild($content);
      }

      $events[] = $event;
    }

    return $events;
  }

  public function render() {
    if (!$this->getObjectPHID()) {
      throw new Exception("Call setObjectPHID() before render()!");
    }

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
          'objectPHID'  => $this->getObjectPHID(),
          'nextAnchor'  => $this->anchorOffset + count($events),
        ));
    }

    return $view->render();
  }

  protected function getOrBuildEngine() {
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

  private function buildChangeDetails(
    PhabricatorApplicationTransaction $xaction) {

    Javelin::initBehavior('phabricator-reveal-content');

    $show_id = celerity_generate_unique_node_id();
    $hide_id = celerity_generate_unique_node_id();
    $content_id = celerity_generate_unique_node_id();

    $show_more = javelin_tag(
      'a',
      array(
        'href' => '#',
        'sigil' => 'reveal-content',
        'mustcapture' => true,
        'id' => $show_id,
        'style' => 'display: none',
        'meta' => array(
          'hideIDs' => array($show_id),
          'showIDs' => array($hide_id, $content_id),
        ),
      ),
      pht('(Show Details)'));

    $hide_more = javelin_tag(
      'a',
      array(
        'href' => '#',
        'sigil' => 'reveal-content',
        'mustcapture' => true,
        'id' => $hide_id,
        'meta' => array(
          'hideIDs' => array($hide_id, $content_id),
          'showIDs' => array($show_id),
        ),
      ),
      pht('(Hide Details)'));

    $content = phutil_tag(
      'div',
      array(
        'id'    => $content_id,
        'class' => 'phabricator-timeline-change-details',
      ),
      $xaction->renderChangeDetails($this->getUser()));

    return array(
      $show_more,
      $hide_more,
      $content,
    );
  }

  private function buildChangeDetailsLink(
    PhabricatorApplicationTransaction $xaction) {

    return javelin_tag(
      'a',
      array(
        'href' => '/transactions/detail/'.$xaction->getPHID().'/',
        'sigil' => 'transaction-detail',
        'mustcapture' => true,
        'meta' => array(
          'anchor' => $this->anchorOffset,
        ),
      ),
      pht('(Show Details)'));
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
      if ($comment->getIsDeleted()) {
        return phutil_tag(
          'em',
          array(),
          pht('This comment has been deleted.'));
      } else {
        return $engine->getOutput($comment, $field);
      }
    }

    return null;
  }

}


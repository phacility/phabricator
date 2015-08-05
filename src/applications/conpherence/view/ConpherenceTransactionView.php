<?php

final class ConpherenceTransactionView extends AphrontView {

  private $conpherenceThread;
  private $conpherenceTransaction;
  private $handles;
  private $markupEngine;
  private $fullDisplay;
  private $classes = array();
  private $timeOnly;

  public function setConpherenceThread(ConpherenceThread $t) {
    $this->conpherenceThread = $t;
    return $this;
  }

  private function getConpherenceThread() {
    return $this->conpherenceThread;
  }

  public function setConpherenceTransaction(ConpherenceTransaction $tx) {
    $this->conpherenceTransaction = $tx;
    return $this;
  }

  private function getConpherenceTransaction() {
    return $this->conpherenceTransaction;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function getHandles() {
    return $this->handles;
  }

  public function setMarkupEngine(PhabricatorMarkupEngine $markup_engine) {
    $this->markupEngine = $markup_engine;
    return $this;
  }

  private function getMarkupEngine() {
    return $this->markupEngine;
  }

  public function setFullDisplay($bool) {
    $this->fullDisplay = $bool;
    return $this;
  }

  private function getFullDisplay() {
    return $this->fullDisplay;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function render() {
    $viewer = $this->getUser();
    if (!$viewer) {
      throw new PhutilInvalidStateException('setUser');
    }

    require_celerity_resource('conpherence-transaction-css');

    $transaction = $this->getConpherenceTransaction();
    switch ($transaction->getTransactionType()) {
      case ConpherenceTransaction::TYPE_DATE_MARKER:
        return javelin_tag(
          'div',
          array(
            'class' => 'conpherence-transaction-view date-marker',
            'sigil' => 'conpherence-transaction-view',
            'meta' => array(
              'id' => $transaction->getID() + 0.5,
            ),
          ),
          array(
            phutil_tag(
              'span',
              array(
                'class' => 'date',
              ),
              phabricator_format_local_time(
                $transaction->getDateCreated(),
                $viewer,
              'M jS, Y')),
          ));
        break;
    }

    $info = $this->renderTransactionInfo();
    $actions = $this->renderTransactionActions();
    $image = $this->renderTransactionImage();
    $content = $this->renderTransactionContent();
    $classes = implode(' ', $this->classes);

    $transaction_dom_id = null;
    if ($this->getFullDisplay()) {
      $transaction_dom_id = 'anchor-'.$transaction->getID();
    }

    $header = phutil_tag_div(
      'conpherence-transaction-header grouped',
      array($actions, $info));

    return javelin_tag(
      'div',
      array(
        'class' => 'conpherence-transaction-view '.$classes,
        'id'    => $transaction_dom_id,
        'sigil' => 'conpherence-transaction-view',
        'meta' => array(
          'id' => $transaction->getID(),
        ),
      ),
      array(
        $image,
        phutil_tag_div('conpherence-transaction-detail grouped',
          array($header, $content)),
      ));
  }

  private function renderTransactionInfo() {
    $viewer = $this->getUser();
    $thread = $this->getConpherenceThread();
    $transaction = $this->getConpherenceTransaction();
    $info = array();

    if ($this->getFullDisplay() && $transaction->getContentSource()) {
      $content_source = id(new PhabricatorContentSourceView())
        ->setContentSource($transaction->getContentSource())
        ->setUser($viewer)
        ->render();
      if ($content_source) {
        $info[] = $content_source;
      }
    }

    Javelin::initBehavior('phabricator-tooltips');
    $tip = phabricator_datetime($transaction->getDateCreated(), $viewer);
    $label = phabricator_time($transaction->getDateCreated(), $viewer);
    $width = 360;
    if ($this->getFullDisplay()) {
      Javelin::initBehavior('phabricator-watch-anchor');
      $anchor = id(new PhabricatorAnchorView())
        ->setAnchorName($transaction->getID())
        ->render();

      $info[] = hsprintf(
        '%s%s',
        $anchor,
        javelin_tag(
          'a',
          array(
            'href'  => '#'.$transaction->getID(),
            'class' => 'anchor-link',
            'sigil' => 'has-tooltip',
            'meta' => array(
              'tip' => $tip,
              'size' => $width,
            ),
          ),
          $label));
    } else {
      $href = '/'.$thread->getMonogram().'#'.$transaction->getID();
      $info[] = javelin_tag(
        'a',
        array(
          'href' => $href,
          'class' => 'epoch-link',
          'sigil' => 'has-tooltip',
          'meta' => array(
            'tip' => $tip,
            'size' => $width,
          ),
        ),
        $label);
    }

    $info = phutil_implode_html(" \xC2\xB7 ", $info);

    return phutil_tag(
      'span',
      array(
        'class' => 'conpherence-transaction-info',
      ),
      $info);
  }

  private function renderTransactionActions() {
    $transaction = $this->getConpherenceTransaction();

    switch ($transaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
        $handles = $this->getHandles();
        $author = $handles[$transaction->getAuthorPHID()];
        $actions = array($author->renderLink());
        break;
      default:
        $actions = null;
        break;
    }

    return $actions;
  }

  private function renderTransactionImage() {
    $image = null;
    if ($this->getFullDisplay()) {
      $transaction = $this->getConpherenceTransaction();
      switch ($transaction->getTransactionType()) {
        case PhabricatorTransactions::TYPE_COMMENT:
          $handles = $this->getHandles();
          $author = $handles[$transaction->getAuthorPHID()];
          $image_uri = $author->getImageURI();
          $image = phutil_tag(
            'span',
            array(
              'class' => 'conpherence-transaction-image',
              'style' => 'background-image: url('.$image_uri.');',
            ));
          break;
      }
    }
    return $image;
  }

  private function renderTransactionContent() {
    $transaction = $this->getConpherenceTransaction();
    $content = null;
    $content_class = null;
    $content = null;
    $handles = $this->getHandles();
    switch ($transaction->getTransactionType()) {
      case ConpherenceTransaction::TYPE_FILES:
        $content = $transaction->getTitle();
        break;
      case ConpherenceTransaction::TYPE_TITLE:
      case ConpherenceTransaction::TYPE_PICTURE:
      case ConpherenceTransaction::TYPE_PICTURE_CROP:
      case ConpherenceTransaction::TYPE_PARTICIPANTS:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
      case PhabricatorTransactions::TYPE_EDGE:
        $content = $transaction->getTitle();
        $this->addClass('conpherence-edited');
        break;
      case PhabricatorTransactions::TYPE_COMMENT:
        $this->addClass('conpherence-comment');
        $author = $handles[$transaction->getAuthorPHID()];
        $comment = $transaction->getComment();
        $content = $this->getMarkupEngine()->getOutput(
          $comment,
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
        $content_class = 'conpherence-message';
        break;
    }

    $this->appendChild(
      phutil_tag(
        'div',
        array(
          'class' => $content_class,
        ),
        $content));

    return phutil_tag_div(
      'conpherence-transaction-content',
      $this->renderChildren());
  }

}

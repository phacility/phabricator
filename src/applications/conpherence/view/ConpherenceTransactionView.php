<?php

final class ConpherenceTransactionView extends AphrontView {

  private $conpherenceThread;
  private $conpherenceTransaction;
  private $handles;
  private $markupEngine;
  private $epoch;
  private $epochHref;
  private $contentSource;
  private $anchorName;
  private $anchorText;
  private $classes = array();
  private $timeOnly;
  private $showImages = true;

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

  public function setEpoch($epoch, $epoch_href = null) {
    $this->epoch = $epoch;
    $this->epochHref = $epoch_href;
    return $this;
  }

  public function setContentSource(PhabricatorContentSource $source) {
    $this->contentSource = $source;
    return $this;
  }

  private function getContentSource() {
    return $this->contentSource;
  }

  public function setAnchor($anchor_name, $anchor_text) {
    $this->anchorName = $anchor_name;
    $this->anchorText = $anchor_text;
    return $this;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function setTimeOnly($time) {
    $this->timeOnly = $time;
    return $this;
  }

  public function setShowImages($bool) {
    $this->showImages = $bool;
    return $this;
  }

  private function getShowImages() {
    return $this->showImages;
  }

  public function render() {
    $user = $this->getUser();
    if (!$user) {
      throw new Exception(pht('Call setUser() before render()!'));
    }

    require_celerity_resource('conpherence-transaction-css');

    $transaction = $this->getConpherenceTransaction();
    switch ($transaction->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_DATE_MARKER:
        return phutil_tag(
          'div',
          array(
            'class' => 'conpherence-transaction-view date-marker',
          ),
          array(
            phutil_tag(
              'span',
              array(
                'class' => 'date',
              ),
              phabricator_format_local_time(
                $transaction->getDateCreated(),
                $user,
              'M jS, Y')),
          ));
        break;
    }

    $info = $this->renderTransactionInfo();
    $actions = $this->renderTransactionActions();
    $image = $this->renderTransactionImage();
    $content = $this->renderTransactionContent();
    $classes = implode(' ', $this->classes);

    $transaction_id = $this->anchorName ? 'anchor-'.$this->anchorName : null;

    $header = phutil_tag_div(
      'conpherence-transaction-header grouped',
      array($actions, $info));

    return phutil_tag(
      'div',
      array(
        'class' => 'conpherence-transaction-view '.$classes,
        'id'    => $transaction_id,
      ),
      array(
        $image,
        phutil_tag_div('conpherence-transaction-detail grouped',
          array($header, $content)),
      ));
  }

  private function renderTransactionInfo() {
    $info = array();

    if ($this->getContentSource()) {
      $content_source = id(new PhabricatorContentSourceView())
        ->setContentSource($this->getContentSource())
        ->setUser($this->user)
        ->render();
      if ($content_source) {
        $info[] = $content_source;
      }
    }

    if ($this->epoch) {
      if ($this->timeOnly) {
        $epoch = phabricator_time($this->epoch, $this->user);
      } else {
        $epoch = phabricator_datetime($this->epoch, $this->user);
      }
      if ($this->epochHref) {
        $epoch = phutil_tag(
          'a',
          array(
            'href' => $this->epochHref,
            'class' => 'epoch-link',
          ),
          $epoch);
      }
      $info[] = $epoch;
    }

    if ($this->anchorName) {
      Javelin::initBehavior('phabricator-watch-anchor');

      $anchor = id(new PhabricatorAnchorView())
        ->setAnchorName($this->anchorName)
        ->render();

      $info[] = hsprintf(
        '%s%s',
        $anchor,
        phutil_tag(
          'a',
          array(
            'href'  => '#'.$this->anchorName,
            'class' => 'anchor-link',
          ),
          $this->anchorText));
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
    if ($this->getShowImages()) {
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
      case ConpherenceTransactionType::TYPE_FILES:
        $content = $transaction->getTitle();
        break;
      case ConpherenceTransactionType::TYPE_TITLE:
      case ConpherenceTransactionType::TYPE_PICTURE:
      case ConpherenceTransactionType::TYPE_PICTURE_CROP:
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
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

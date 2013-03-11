<?php

/**
 * @group conpherence
 */
final class ConpherenceTransactionView extends AphrontView {

  private $conpherenceTransaction;
  private $handles;
  private $markupEngine;

  public function setMarkupEngine(PhabricatorMarkupEngine $markup_engine) {
    $this->markupEngine = $markup_engine;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }
  public function getHandles() {
    return $this->handles;
  }

  public function setConpherenceTransaction(ConpherenceTransaction $tx) {
    $this->conpherenceTransaction = $tx;
    return $this;
  }
  private function getConpherenceTransaction() {
    return $this->conpherenceTransaction;
  }

  public function render() {
    $transaction = $this->getConpherenceTransaction();
    $handles = $this->getHandles();
    $transaction->setHandles($handles);
    $author = $handles[$transaction->getAuthorPHID()];
    $transaction_view = id(new PhabricatorTransactionView())
      ->setUser($this->getUser())
      ->setEpoch($transaction->getDateCreated())
      ->setContentSource($transaction->getContentSource());

    $content = null;
    $content_class = null;
    $content = null;
    switch ($transaction->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_TITLE:
      case ConpherenceTransactionType::TYPE_PICTURE:
      case ConpherenceTransactionType::TYPE_PICTURE_CROP:
        $content = $transaction->getTitle();
        $transaction_view->addClass('conpherence-edited');
        break;
      case ConpherenceTransactionType::TYPE_FILES:
        $content = $transaction->getTitle();
        break;
      case ConpherenceTransactionType::TYPE_PICTURE:
        $img = $transaction->getHandle($transaction->getNewValue());
        $content = array(
          $transaction->getTitle(),
          phutil_tag(
            'img',
            array(
              'src' => $img->getImageURI()
            )));
        $transaction_view->addClass('conpherence-edited');
        break;
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
        $content = $transaction->getTitle();
        $transaction_view->addClass('conpherence-edited');
        break;
      case PhabricatorTransactions::TYPE_COMMENT:
        $comment = $transaction->getComment();
        $content = $this->markupEngine->getOutput(
          $comment,
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
        $content_class = 'conpherence-message phabricator-remarkup';
        $transaction_view
          ->setImageURI($author->getImageURI())
          ->setActions(array($author->renderLink()));
        break;
    }

    $transaction_view->appendChild(
      phutil_tag(
        'div',
        array(
          'class' => $content_class
        ),
        $content));

    return $transaction_view->render();
  }
}

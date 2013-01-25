<?php

/**
 * @group conpherence
 */
final class ConpherenceTransactionView extends AphrontView {

  private $conpherenceTransaction;
  private $handles;

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

    $content_class = null;
    switch ($transaction->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_TITLE:
        $content = $transaction->getTitle();
        $transaction_view->addClass('conpherence-edited');
        break;
      case ConpherenceTransactionType::TYPE_FILES:
        $content = $transaction->getTitle();
        break;
      case ConpherenceTransactionType::TYPE_PICTURE:
        $img = $transaction->getHandle($transaction->getNewValue());
        $content = $transaction->getTitle() .
          phutil_render_tag(
            'img',
            array(
              'src' => $img->getImageURI()
            )
          );
        $transaction_view->addClass('conpherence-edited');
        break;
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
        $content = $transaction->getTitle();
        $transaction_view->addClass('conpherence-edited');
        break;
      case PhabricatorTransactions::TYPE_COMMENT:
        $comment = $transaction->getComment();
        $file_ids =
          PhabricatorMarkupEngine::extractFilePHIDsFromEmbeddedFiles(
            array($comment->getContent())
          );
        $markup_field = ConpherenceTransactionComment::MARKUP_FIELD_COMMENT;
        $engine = id(new PhabricatorMarkupEngine())
          ->setViewer($this->getUser());
        $engine->addObject(
          $comment,
          $markup_field
        );
        $engine->process();
        $content = $engine->getOutput(
          $comment,
          $markup_field
        );
        $content_class = 'conpherence-message phabricator-remarkup';
        $transaction_view
          ->setImageURI($author->getImageURI())
          ->setActions(array($author->renderLink()));
        break;
    }

    $transaction_view
      ->appendChild(phutil_render_tag(
        'div',
        array(
          'class' => $content_class
        ),
        $content)
      );

    return $transaction_view->render();
  }
}

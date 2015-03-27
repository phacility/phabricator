<?php

final class PhabricatorApplicationTransactionCommentHistoryController
  extends PhabricatorApplicationTransactionController {

  private $phid;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $xaction = id(new PhabricatorObjectQuery())
      ->withPHIDs(array($this->phid))
      ->setViewer($user)
      ->executeOne();

    if (!$xaction) {
      return new Aphront404Response();
    }

    if (!$xaction->getComment()) {
      // You can't view history of a transaction with no comments.
      return new Aphront404Response();
    }

    if ($xaction->getComment()->getIsRemoved()) {
      // You can't view history of a transaction with a removed comment.
      return new Aphront400Response();
    }

    $comments = id(new PhabricatorApplicationTransactionTemplatedCommentQuery())
      ->setViewer($user)
      ->setTemplate($xaction->getApplicationTransactionCommentObject())
      ->withTransactionPHIDs(array($xaction->getPHID()))
      ->execute();

    if (!$comments) {
      return new Aphront404Response();
    }

    $comments = msort($comments, 'getCommentVersion');

    $xactions = array();
    foreach ($comments as $comment) {
      $xactions[] = id(clone $xaction)
        ->makeEphemeral()
        ->setCommentVersion($comment->getCommentVersion())
        ->setContentSource($comment->getContentSource())
        ->setDateCreated($comment->getDateCreated())
        ->attachComment($comment);
    }

    $obj_phid = $xaction->getObjectPHID();
    $obj_handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($obj_phid))
      ->executeOne();

    $view = id(new PhabricatorApplicationTransactionView())
      ->setUser($user)
      ->setObjectPHID($obj_phid)
      ->setTransactions($xactions)
      ->setShowEditActions(false)
      ->setHideCommentOptions(true);

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setWidth(AphrontDialogView::WIDTH_FULL)
      ->setFlush(true)
      ->setTitle(pht('Comment History'));

    $dialog->appendChild($view);

    $dialog
      ->addCancelButton($obj_handle->getURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}

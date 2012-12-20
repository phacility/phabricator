<?php

final class PhabricatorApplicationTransactionCommentHistoryController
  extends PhabricatorApplicationTransactionController {

  private $phid;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $xactions = id(new PhabricatorObjectHandleData(array($this->phid)))
      ->setViewer($user)
      ->loadObjects();
    $xaction = idx($xactions, $this->phid);

    if (!$xaction) {
      // TODO: This may also mean you don't have permission to edit the object,
      // but we can't make that distinction via PhabricatorObjectHandleData
      // at the moment.
      return new Aphront404Response();
    }

    if (!$xaction->getComment()) {
      // You can't view history of a transaction with no comments.
      return new Aphront404Response();
    }

    $obj_phid = $xaction->getObjectPHID();
    $obj_handle = PhabricatorObjectHandleData::loadOneHandle($obj_phid, $user);
    if (!$obj_handle) {
      // Require the corresponding object exist and be visible to the user.
      return new Aphront404Response();
    }

    $comments = id(new PhabricatorApplicationTransactionCommentQuery())
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

    $view = id(new PhabricatorApplicationTransactionView())
      ->setUser($user)
      ->setTransactions($xactions)
      ->setShowEditActions(false);


    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setWidth(AphrontDialogView::WIDTH_FULL)
      ->setTitle(pht('Comment History'));

    $dialog->appendChild($view);

    $dialog
      ->addCancelButton($obj_handle->getURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}

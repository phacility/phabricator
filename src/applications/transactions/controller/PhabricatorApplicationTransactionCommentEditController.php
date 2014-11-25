<?php

final class PhabricatorApplicationTransactionCommentEditController
  extends PhabricatorApplicationTransactionController {

  private $phid;

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
      // You can't currently edit a transaction which doesn't have a comment.
      // Some day you may be able to edit the visibility.
      return new Aphront404Response();
    }

    if ($xaction->getComment()->getIsRemoved()) {
      // You can't edit history of a transaction with a removed comment.
      return new Aphront400Response();
    }

    $obj_phid = $xaction->getObjectPHID();
    $obj_handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($obj_phid))
      ->executeOne();

    if ($request->isDialogFormPost()) {
      $text = $request->getStr('text');

      $comment = $xaction->getApplicationTransactionCommentObject();
      $comment->setContent($text);
      if (!strlen($text)) {
        $comment->setIsDeleted(true);
      }

      $editor = id(new PhabricatorApplicationTransactionCommentEditor())
        ->setActor($user)
        ->setContentSource(PhabricatorContentSource::newFromRequest($request))
        ->applyEdit($xaction, $comment);

      if ($request->isAjax()) {
        return id(new AphrontAjaxResponse())->setContent(array());
      } else {
        return id(new AphrontReloadResponse())->setURI($obj_handle->getURI());
      }
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setSubmitURI(
        $this->getApplicationURI('/transactions/edit/'.$xaction->getPHID().'/'))
      ->setTitle(pht('Edit Comment'));

    $dialog
      ->addHiddenInput('anchor', $request->getStr('anchor'))
      ->appendChild(
        id(new PHUIFormLayoutView())
        ->setFullWidth(true)
        ->appendChild(
          id(new PhabricatorRemarkupControl())
          ->setUser($user)
          ->setName('text')
          ->setValue($xaction->getComment()->getContent())));

    $dialog
      ->addSubmitButton(pht('Save Changes'))
      ->addCancelButton($obj_handle->getURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}

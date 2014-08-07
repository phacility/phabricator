<?php

final class PhabricatorApplicationTransactionCommentRawController
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
      // You can't view a raw comment if there is no comment.
      return new Aphront404Response();
    }

    if ($xaction->getComment()->getIsRemoved()) {
      // You can't view a raw comment if the comment is deleted.
      return new Aphront400Response();
    }

    $obj_phid = $xaction->getObjectPHID();
    $obj_handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($obj_phid))
      ->executeOne();

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->addCancelButton($obj_handle->getURI())
      ->setTitle(pht('Raw Comment'));

    $dialog
      ->addHiddenInput('anchor', $request->getStr('anchor'))
      ->appendChild(
        id(new PHUIFormLayoutView())
        ->setFullWidth(true)
        ->appendChild(
          id(new AphrontFormTextAreaControl())
          ->setReadOnly(true)
          ->setValue($xaction->getComment()->getContent())));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}

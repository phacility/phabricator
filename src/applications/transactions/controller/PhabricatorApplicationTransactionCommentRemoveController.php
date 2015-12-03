<?php

final class PhabricatorApplicationTransactionCommentRemoveController
  extends PhabricatorApplicationTransactionController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $phid = $request->getURIData('phid');

    $xaction = id(new PhabricatorObjectQuery())
      ->withPHIDs(array($phid))
      ->setViewer($viewer)
      ->executeOne();
    if (!$xaction) {
      return new Aphront404Response();
    }

    if (!$xaction->getComment()) {
      return new Aphront404Response();
    }

    if ($xaction->getComment()->getIsRemoved()) {
      // You can't remove an already-removed comment.
      return new Aphront400Response();
    }

    $obj_phid = $xaction->getObjectPHID();
    $obj_handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($obj_phid))
      ->executeOne();

    if ($request->isDialogFormPost()) {
      $comment = $xaction->getApplicationTransactionCommentObject()
        ->setContent('')
        ->setIsRemoved(true);

      $editor = id(new PhabricatorApplicationTransactionCommentEditor())
        ->setActor($viewer)
        ->setContentSource(PhabricatorContentSource::newFromRequest($request))
        ->applyEdit($xaction, $comment);

      if ($request->isAjax()) {
        return id(new AphrontAjaxResponse())->setContent(array());
      } else {
        return id(new AphrontReloadResponse())->setURI($obj_handle->getURI());
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $dialog = $this->newDialog()
      ->setTitle(pht('Remove Comment'));

    $dialog
      ->addHiddenInput('anchor', $request->getStr('anchor'))
      ->appendParagraph(
        pht(
          "Removing a comment prevents anyone (including you) from reading ".
          "it. Removing a comment also hides the comment's edit history ".
          "and prevents it from being edited."))
      ->appendParagraph(
        pht('Really remove this comment?'));

    $dialog
      ->addSubmitButton(pht('Remove Comment'))
      ->addCancelButton($obj_handle->getURI());

    return $dialog;
  }

}

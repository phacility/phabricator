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

    $done_uri = $obj_handle->getURI();

    // We allow administrative removal of comments even if an object is locked,
    // so you can lock a flamewar and then go clean it up. Locked threads may
    // not otherwise be edited, and non-administrators can not remove comments
    // from locked threads.

    $object = $xaction->getObject();
    $can_interact = PhabricatorPolicyFilter::canInteract(
      $viewer,
      $object);
    if (!$can_interact && !$viewer->getIsAdmin()) {
      return $this->newDialog()
        ->setTitle(pht('Conversation Locked'))
        ->appendParagraph(
          pht(
            'You can not remove this comment because the conversation is '.
            'locked.'))
        ->addCancelButton($done_uri);
    }

    if ($request->isFormOrHisecPost()) {
      $comment = $xaction->getApplicationTransactionCommentObject()
        ->setContent('')
        ->setIsRemoved(true);

      $editor = id(new PhabricatorApplicationTransactionCommentEditor())
        ->setActor($viewer)
        ->setRequest($request)
        ->setCancelURI($done_uri)
        ->setContentSource(PhabricatorContentSource::newFromRequest($request))
        ->applyEdit($xaction, $comment);

      if ($request->isAjax()) {
        return id(new AphrontAjaxResponse())->setContent(array());
      } else {
        return id(new AphrontReloadResponse())->setURI($done_uri);
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $dialog = $this->newDialog()
      ->setTitle(pht('Remove Comment'));

    $dialog
      ->appendParagraph(
        pht(
          "Removing a comment prevents anyone (including you) from reading ".
          "it. Removing a comment also hides the comment's edit history ".
          "and prevents it from being edited."))
      ->appendParagraph(
        pht('Really remove this comment?'));

    $dialog
      ->addSubmitButton(pht('Remove Comment'))
      ->addCancelButton($done_uri);

    return $dialog;
  }

}

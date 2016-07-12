<?php

final class PhabricatorApplicationTransactionCommentEditController
  extends PhabricatorApplicationTransactionController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $xaction = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($request->getURIData('phid')))
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

    $phid = $xaction->getObjectPHID();
    $handles = $viewer->loadHandles(array($phid));
    $obj_handle = $handles[$phid];

    if ($request->isDialogFormPost()) {
      $text = $request->getStr('text');

      $comment = $xaction->getApplicationTransactionCommentObject();
      $comment->setContent($text);
      if (!strlen($text)) {
        $comment->setIsDeleted(true);
      }

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
      ->setUser($viewer)
      ->setFullWidth(true)
      ->appendControl(
        id(new PhabricatorRemarkupControl())
        ->setName('text')
        ->setValue($xaction->getComment()->getContent()));

    return $this->newDialog()
      ->setTitle(pht('Edit Comment'))
      ->addHiddenInput('anchor', $request->getStr('anchor'))
      ->appendForm($form)
      ->addSubmitButton(pht('Save Changes'))
      ->addCancelButton($obj_handle->getURI());
  }

}

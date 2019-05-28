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

    $done_uri = $obj_handle->getURI();

    // If an object is locked, you can't edit comments on it. Two reasons to
    // lock threads are to calm contentious issues and to freeze state for
    // auditing, and editing comments serves neither goal.

    $object = $xaction->getObject();
    $can_interact = PhabricatorPolicyFilter::canInteract(
      $viewer,
      $object);
    if (!$can_interact) {
      return $this->newDialog()
        ->setTitle(pht('Conversation Locked'))
        ->appendParagraph(
          pht(
            'You can not edit this comment because the conversation is '.
            'locked.'))
        ->addCancelButton($done_uri);
    }

    if ($request->isFormOrHisecPost()) {
      $text = $request->getStr('text');

      $comment = $xaction->getApplicationTransactionCommentObject();
      $comment->setContent($text);
      if (!strlen($text)) {
        $comment->setIsDeleted(true);
      }

      $editor = id(new PhabricatorApplicationTransactionCommentEditor())
        ->setActor($viewer)
        ->setContentSource(PhabricatorContentSource::newFromRequest($request))
        ->setRequest($request)
        ->setCancelURI($done_uri)
        ->applyEdit($xaction, $comment);

      if ($request->isAjax()) {
        return id(new AphrontAjaxResponse())->setContent(array());
      } else {
        return id(new AphrontReloadResponse())->setURI($done_uri);
      }
    }

    $errors = array();
    if ($xaction->getIsMFATransaction()) {
      $message = pht(
        'This comment was signed with MFA, so you will be required to '.
        'provide MFA credentials to make changes.');

      $errors[] = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_MFA)
        ->setErrors(array($message));
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
      ->appendChild($errors)
      ->appendForm($form)
      ->addSubmitButton(pht('Save Changes'))
      ->addCancelButton($done_uri);
  }

}

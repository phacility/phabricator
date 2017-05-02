<?php

final class PhameBlogArchiveController
  extends PhameBlogController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $blog = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    $view_uri = $this->getApplicationURI('blog/manage/'.$blog->getID().'/');

    if ($request->isFormPost()) {
      if ($blog->isArchived()) {
        $new_status = PhameBlog::STATUS_ACTIVE;
      } else {
        $new_status = PhameBlog::STATUS_ARCHIVED;
      }

      $xactions = array();

      $xactions[] = id(new PhameBlogTransaction())
        ->setTransactionType(PhameBlogStatusTransaction::TRANSACTIONTYPE)
        ->setNewValue($new_status);

      id(new PhameBlogEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($blog, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    if ($blog->isArchived()) {
      $title = pht('Activate Blog');
      $body = pht('This blog will become active again.');
      $button = pht('Activate Blog');
    } else {
      $title = pht('Archive Blog');
      $body = pht('This blog will be marked as archived.');
      $button = pht('Archive Blog');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle($title)
      ->appendChild($body)
      ->addCancelButton($view_uri)
      ->addSubmitButton($button);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}

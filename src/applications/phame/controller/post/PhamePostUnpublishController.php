<?php

final class PhamePostUnpublishController extends PhamePostController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $post = id(new PhamePostQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$post) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $xactions = array();
      $xactions[] = id(new PhamePostTransaction())
        ->setTransactionType(PhamePostTransaction::TYPE_VISIBILITY)
        ->setNewValue(PhameConstants::VISIBILITY_DRAFT);

      id(new PhamePostEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($post, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($this->getApplicationURI('/post/view/'.$post->getID().'/'));
    }

    $cancel_uri = $this->getApplicationURI('/post/view/'.$post->getID().'/');

    $dialog = $this->newDialog()
      ->setTitle(pht('Unpublish Post?'))
      ->appendChild(
        pht(
          'The post "%s" will no longer be visible to other users until you '.
          'republish it.',
          $post->getTitle()))
      ->addSubmitButton(pht('Unpublish'))
      ->addCancelButton($cancel_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}

<?php

final class HeraldWebhookTestController
  extends HeraldWebhookController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $hook = id(new HeraldWebhookQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$hook) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $object = $hook;

      $request = HeraldWebhookRequest::initializeNewWebhookRequest($hook)
        ->setObjectPHID($object->getPHID())
        ->save();

      $request->queueCall();

      $next_uri = $hook->getURI().'request/'.$request->getID().'/';

      return id(new AphrontRedirectResponse())->setURI($next_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('New Test Request'))
      ->appendParagraph(
        pht('This will make a new test request to the configured URI.'))
      ->addCancelButton($hook->getURI())
      ->addSubmitButton(pht('Make Request'));
  }


}

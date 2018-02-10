<?php

final class HeraldWebhookKeyController
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

    $action = $request->getURIData('action');
    if ($action === 'cycle') {
      if (!$request->isFormPost()) {
        return $this->newDialog()
          ->setTitle(pht('Regenerate HMAC Key'))
          ->appendParagraph(
            pht(
              'Regenerate the HMAC key used to sign requests made by this '.
              'webhook?'))
          ->appendParagraph(
            pht(
              'Requests which are currently authenticated with the old key '.
              'may fail.'))
          ->addCancelButton($hook->getURI())
          ->addSubmitButton(pht('Regnerate Key'));
      } else {
        $hook->regenerateHMACKey()->save();
      }
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendControl(
        id(new AphrontFormTextControl())
          ->setLabel(pht('HMAC Key'))
          ->setValue($hook->getHMACKey()));

    return $this->newDialog()
      ->setTitle(pht('Webhook HMAC Key'))
      ->appendForm($form)
      ->addCancelButton($hook->getURI(), pht('Done'));
  }


}

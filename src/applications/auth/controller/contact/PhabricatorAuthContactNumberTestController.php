<?php

final class PhabricatorAuthContactNumberTestController
  extends PhabricatorAuthContactNumberController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $number = id(new PhabricatorAuthContactNumberQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$number) {
      return new Aphront404Response();
    }

    $id = $number->getID();
    $cancel_uri = $number->getURI();

    // NOTE: This is a global limit shared by all users.
    PhabricatorSystemActionEngine::willTakeAction(
      array(id(new PhabricatorAuthApplication())->getPHID()),
      new PhabricatorAuthTestSMSAction(),
      1);

    if ($request->isFormPost()) {
      $uri = PhabricatorEnv::getURI('/');
      $uri = new PhutilURI($uri);

      $mail = id(new PhabricatorMetaMTAMail())
        ->setMessageType(PhabricatorMailSMSMessage::MESSAGETYPE)
        ->addTos(array($viewer->getPHID()))
        ->setSensitiveContent(false)
        ->setBody(
          pht(
            'This is a terse test text message (from "%s").',
            $uri->getDomain()))
        ->save();

      return id(new AphrontRedirectResponse())->setURI($mail->getURI());
    }

    $number_display = phutil_tag(
      'strong',
      array(),
      $number->getDisplayName());

    return $this->newDialog()
      ->setTitle(pht('Set Test Message'))
      ->appendParagraph(
        pht(
          'Send a test message to %s?',
          $number_display))
      ->addSubmitButton(pht('Send SMS'))
      ->addCancelButton($cancel_uri);
  }

}

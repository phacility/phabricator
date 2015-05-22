<?php

final class LegalpadDocumentSignatureVerificationController
  extends LegalpadController {

  private $code;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->code = $data['code'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    // NOTE: We're using the omnipotent user to handle logged-out signatures
    // and corporate signatures.
    $signature = id(new LegalpadDocumentSignatureQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withSecretKeys(array($this->code))
      ->executeOne();

    if (!$signature) {
      return $this->newDialog()
        ->setTitle(pht('Unable to Verify Signature'))
        ->appendParagraph(
          pht(
            'The signature verification code is incorrect, or the signature '.
            'has been invalidated. Make sure you followed the link in the '.
            'email correctly.'))
        ->addCancelButton('/', pht('Rats!'));
    }

    if ($signature->isVerified()) {
      return $this->newDialog()
        ->setTitle(pht('Signature Already Verified'))
        ->appendParagraph(pht('This signature has already been verified.'))
        ->addCancelButton('/', pht('Okay'));
    }

    if ($request->isFormPost()) {
      $signature
        ->setVerified(LegalpadDocumentSignature::VERIFIED)
        ->save();

      return $this->newDialog()
        ->setTitle(pht('Signature Verified'))
        ->appendParagraph(pht('The signature is now verified.'))
        ->addCancelButton('/', pht('Okay'));
    }

    $document_link = phutil_tag(
      'a',
      array(
        'href' => '/'.$signature->getDocument()->getMonogram(),
        'target' => '_blank',
      ),
      $signature->getDocument()->getTitle());

    $signed_at = phabricator_datetime($signature->getDateCreated(), $viewer);

    $name = $signature->getSignerName();
    $email = $signature->getSignerEmail();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht('Please verify this document signature.'))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Document'))
          ->setValue($document_link))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Signed At'))
          ->setValue($signed_at))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Name'))
          ->setValue($name))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Email'))
          ->setValue($email));

    return $this->newDialog()
      ->setTitle(pht('Verify Signature?'))
      ->appendChild($form->buildLayoutView())
      ->addCancelButton('/')
      ->addSubmitButton(pht('Verify Signature'));
  }

}

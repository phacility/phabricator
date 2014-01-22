<?php

final class LegalpadDocumentSignatureVerificationController
extends LegalpadController {

  private $code;

  public function willProcessRequest(array $data) {
    $this->code = $data['code'];
  }

  public function shouldRequireEmailVerification() {
    return false;
  }

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    // this page can be accessed by not logged in users to valid their
    // signatures. use the omnipotent user for these cases.
    if (!$user->isLoggedIn()) {
      $viewer = PhabricatorUser::getOmnipotentUser();
    } else {
      $viewer = $user;
    }

    $signature = id(new LegalpadDocumentSignatureQuery())
      ->setViewer($viewer)
      ->withSecretKeys(array($this->code))
      ->executeOne();

    if (!$signature) {
      $title = pht('Unable to Verify Signature');
      $content = pht(
        'The verification code you provided is incorrect or the signature '.
        'has been removed. '.
        'Make sure you followed the link in the email correctly.');
      $uri = $this->getApplicationURI();
      $continue = pht('Rats!');
    } else {
      $document = id(new LegalpadDocumentQuery())
        ->setViewer($user)
        ->withPHIDs(array($signature->getDocumentPHID()))
        ->executeOne();
      // the document could be deleted or have its permissions changed
      // 4oh4 time
      if (!$document) {
        return new Aphront404Response();
      }
      $uri = '/'.$document->getMonogram();
      if ($signature->isVerified()) {
        $title = pht('Signature Already Verified');
        $content = pht(
          'This signature has already been verified.');
        $continue = pht('Continue to Legalpad Document');
      } else {
        $guard = AphrontWriteGuard::beginScopedUnguardedWrites();
          $signature
            ->setVerified(LegalpadDocumentSignature::VERIFIED)
            ->save();
        unset($guard);
        $title = pht('Signature Verified');
        $content = pht('The signature is now verified.');
        $continue = pht('Continue to Legalpad Document');
      }
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle($title)
      ->setMethod('GET')
      ->addCancelButton($uri, $continue)
      ->appendChild($content);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Verify Signature'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $dialog,
      ),
      array(
        'title' => pht('Verify Signature'),
        'device' => true,
      ));
  }

}

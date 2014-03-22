<?php

final class LegalpadDocumentSignController extends LegalpadController {

  private $id;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $document = id(new LegalpadDocumentQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->needDocumentBodies(true)
      ->executeOne();

    if (!$document) {
      return new Aphront404Response();
    }

    $signer_phid = null;
    $signature = null;
    $signature_data = array();
    if ($user->isLoggedIn()) {
      $signer_phid = $user->getPHID();
      $signature_data = array(
        'email' => $user->loadPrimaryEmailAddress());
    } else if ($request->isFormPost()) {
      $email = new PhutilEmailAddress($request->getStr('email'));
      $email_obj = id(new PhabricatorUserEmail())
        ->loadOneWhere('address = %s', $email->getAddress());
      if ($email_obj) {
        return $this->signInResponse();
      }
      $external_account = id(new PhabricatorExternalAccountQuery())
        ->setViewer($user)
        ->withAccountTypes(array('email'))
        ->withAccountDomains(array($email->getDomainName()))
        ->withAccountIDs(array($email->getAddress()))
        ->loadOneOrCreate();
      if ($external_account->getUserPHID()) {
        return $this->signInResponse();
      }
      $signer_phid = $external_account->getPHID();
    }

    if ($signer_phid) {
      $signature = id(new LegalpadDocumentSignatureQuery())
        ->setViewer($user)
        ->withDocumentPHIDs(array($document->getPHID()))
        ->withSignerPHIDs(array($signer_phid))
        ->withDocumentVersions(array($document->getVersions()))
        ->executeOne();
    }

    if (!$signature) {
      $has_signed = false;
      $error_view = null;
      $signature = id(new LegalpadDocumentSignature())
        ->setSignerPHID($signer_phid)
        ->setDocumentPHID($document->getPHID())
        ->setDocumentVersion($document->getVersions())
        ->setSignatureData($signature_data);
    } else {
      $has_signed = true;
      if ($signature->isVerified()) {
        $title = pht('Already Signed');
        $body = $this->getVerifiedSignatureBlurb();
      } else {
        $title = pht('Already Signed but...');
        $body = $this->getUnverifiedSignatureBlurb();
      }
      $error_view = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle($title)
        ->appendChild($body);
      $signature_data = $signature->getSignatureData();
    }

    $e_name = true;
    $e_email = true;
    $e_address_1 = true;
    $errors = array();
    if ($request->isFormPost() && !$has_signed) {
      $name = $request->getStr('name');
      $email = $request->getStr('email');
      $address_1 = $request->getStr('address_1');
      $address_2 = $request->getStr('address_2');
      $phone = $request->getStr('phone');
      $agree = $request->getExists('agree');

      if (!$name) {
        $e_name = pht('Required');
        $errors[] = pht('Name field is required.');
      }
      $signature_data['name'] = $name;

      $addr_obj = null;
      if (!$email) {
        $e_email = pht('Required');
        $errors[] = pht('Email field is required.');
      } else {
        $addr_obj = new PhutilEmailAddress($email);
        $domain = $addr_obj->getDomainName();
        if (!$domain) {
          $e_email = pht('Invalid');
          $errors[] = pht('A valid email is required.');
        }
      }
      $signature_data['email'] = $email;

      if (!$address_1) {
        $e_address_1 = pht('Required');
        $errors[] = pht('Address line 1 field is required.');
      }
      $signature_data['address_1'] = $address_1;
      $signature_data['address_2'] = $address_2;
      $signature_data['phone'] = $phone;
      $signature->setSignatureData($signature_data);

      if (!$agree) {
        $errors[] = pht(
          'You must check "I agree to the terms laid forth above."');
      }

      $verified = LegalpadDocumentSignature::UNVERIFIED;
      if ($user->isLoggedIn() && $addr_obj) {
        $email_obj = id(new PhabricatorUserEmail())
          ->loadOneWhere('address = %s', $addr_obj->getAddress());
        if ($email_obj && $email_obj->getUserPHID() == $user->getPHID()) {
          $verified = LegalpadDocumentSignature::VERIFIED;
        }
      }
      $signature->setVerified($verified);

      if (!$errors) {
        $signature->save();
        $has_signed = true;
        if ($signature->isVerified()) {
          $body = $this->getVerifiedSignatureBlurb();
        } else {
          $body = $this->getUnverifiedSignatureBlurb();
          $this->sendVerifySignatureEmail(
            $document,
            $signature);
        }
        $error_view = id(new AphrontErrorView())
          ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
          ->setTitle(pht('Signature Successful'))
          ->appendChild($body);
      } else {
        $error_view = id(new AphrontErrorView())
          ->setTitle(pht('Error in submission.'))
          ->setErrors($errors);
      }
    }

    $document_body = $document->getDocumentBody();
    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    $engine->addObject(
      $document_body,
      LegalpadDocumentBody::MARKUP_FIELD_TEXT);
    $engine->process();

    $title = $document_body->getTitle();

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $content = array(
      $this->buildDocument(
        $header,
        $engine,
        $document_body),
      $this->buildSignatureForm(
        $document_body,
        $signature,
        $has_signed,
        $e_name,
        $e_email,
        $e_address_1,
        $error_view));

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $title,
        'device' => true,
        'pageObjects' => array($document->getPHID()),
      ));
  }

  private function buildDocument(
    PHUIHeaderView $header,
    PhabricatorMarkupEngine $engine,
    LegalpadDocumentBody $body) {

    return id(new PHUIDocumentView())
      ->addClass('legalpad')
      ->setHeader($header)
      ->appendChild($engine->getOutput(
        $body,
        LegalpadDocumentBody::MARKUP_FIELD_TEXT));
  }

  private function buildSignatureForm(
    LegalpadDocumentBody $body,
    LegalpadDocumentSignature $signature,
    $has_signed = false,
    $e_name = true,
    $e_email = true,
    $e_address_1 = true,
    $error_view = null) {

    $user = $this->getRequest()->getUser();
    if ($has_signed) {
      $instructions = pht('Thank you for signing and agreeing.');
    } else {
      $instructions = pht('Please enter the following information.');
    }

    $data = $signature->getSignatureData();
    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Name'))
        ->setValue(idx($data, 'name', ''))
        ->setName('name')
        ->setError($e_name)
        ->setDisabled($has_signed))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Email'))
        ->setValue(idx($data, 'email', ''))
        ->setName('email')
        ->setError($e_email)
        ->setDisabled($has_signed))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Address line 1'))
        ->setValue(idx($data, 'address_1', ''))
        ->setName('address_1')
        ->setError($e_address_1)
        ->setDisabled($has_signed))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Address line 2'))
        ->setValue(idx($data, 'address_2', ''))
        ->setName('address_2')
        ->setDisabled($has_signed))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Phone'))
        ->setValue(idx($data, 'phone', ''))
        ->setName('phone')
        ->setDisabled($has_signed))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
        ->addCheckbox(
          'agree',
          'agree',
          pht('I agree to the terms laid forth above.'),
          $has_signed)
        ->setDisabled($has_signed))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue(pht('Sign and Agree'))
        ->setDisabled($has_signed));

    $view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Sign and Agree'))
      ->setForm($form);
    if ($error_view) {
      $view->setErrorView($error_view);
    }
    return $view;
  }

  private function getVerifiedSignatureBlurb() {
    return pht('Thank you for signing and agreeing.');
  }

  private function getUnverifiedSignatureBlurb() {
    return pht('Thank you for signing and agreeing. However, you must '.
               'verify your email address. Please check your email '.
               'and follow the instructions.');
  }

  private function sendVerifySignatureEmail(
    LegalpadDocument $doc,
    LegalpadDocumentSignature $signature) {

    $signature_data = $signature->getSignatureData();
    $email = new PhutilEmailAddress($signature_data['email']);
    $doc_link = PhabricatorEnv::getProductionURI($doc->getMonogram());
    $path = $this->getApplicationURI(sprintf(
      '/verify/%s/',
      $signature->getSecretKey()));
    $link = PhabricatorEnv::getProductionURI($path);

    $body = <<<EOBODY
Hi {$signature_data['name']},

This email address was used to sign a Legalpad document ({$doc_link}).
Please verify you own this email address by clicking this link:

  {$link}

Your signature is invalid until you verify you own the email.
EOBODY;

    id(new PhabricatorMetaMTAMail())
      ->addRawTos(array($email->getAddress()))
      ->setSubject(pht('[Legalpad] Signature Verification'))
      ->setBody($body)
      ->setRelatedPHID($signature->getDocumentPHID())
      ->saveAndSend();
  }

  private function signInResponse() {
    return id(new Aphront403Response())
      ->setForbiddenText(pht(
        'The email address specified is associated with an account. '.
        'Please login to that account and sign this document again.'));
  }

}

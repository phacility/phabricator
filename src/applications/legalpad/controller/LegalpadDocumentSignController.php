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
    $viewer = $request->getUser();

    $document = id(new LegalpadDocumentQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needDocumentBodies(true)
      ->executeOne();
    if (!$document) {
      return new Aphront404Response();
    }

    $signer_phid = null;
    $signature_data = array();
    if ($viewer->isLoggedIn()) {
      $signer_phid = $viewer->getPHID();
      $signature_data = array(
        'name' => $viewer->getRealName(),
        'email' => $viewer->loadPrimaryEmailAddress(),
      );
    } else if ($request->isFormPost()) {
      $email = new PhutilEmailAddress($request->getStr('email'));
      if (strlen($email->getDomainName())) {
        $email_obj = id(new PhabricatorUserEmail())
          ->loadOneWhere('address = %s', $email->getAddress());
        if ($email_obj) {
          return $this->signInResponse();
        }
        $external_account = id(new PhabricatorExternalAccountQuery())
          ->setViewer($viewer)
          ->withAccountTypes(array('email'))
          ->withAccountDomains(array($email->getDomainName()))
          ->withAccountIDs(array($email->getAddress()))
          ->loadOneOrCreate();
        if ($external_account->getUserPHID()) {
          return $this->signInResponse();
        }
        $signer_phid = $external_account->getPHID();
      }
    }

    $signature = null;
    if ($signer_phid) {
      // TODO: This is odd and should probably be adjusted after grey/external
      // accounts work better, but use the omnipotent viewer to check for a
      // signature so we can pick up anonymous/grey signatures.

      $signature = id(new LegalpadDocumentSignatureQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withDocumentPHIDs(array($document->getPHID()))
        ->withSignerPHIDs(array($signer_phid))
        ->withDocumentVersions(array($document->getVersions()))
        ->executeOne();

      if ($signature && !$viewer->isLoggedIn()) {
        return $this->newDialog()
          ->setTitle(pht('Already Signed'))
          ->appendParagraph(pht('You have already signed this document!'))
          ->addCancelButton('/'.$document->getMonogram(), pht('Okay'));
      }
    }

    $signed_status = null;
    if (!$signature) {
      $has_signed = false;
      $signature = id(new LegalpadDocumentSignature())
        ->setSignerPHID($signer_phid)
        ->setDocumentPHID($document->getPHID())
        ->setDocumentVersion($document->getVersions())
        ->setSignatureData($signature_data);

      // If the user is logged in, show a notice that they haven't signed.
      // If they aren't logged in, we can't be as sure, so don't show anything.
      if ($viewer->isLoggedIn()) {
        $signed_status = id(new AphrontErrorView())
          ->setSeverity(AphrontErrorView::SEVERITY_WARNING)
          ->setErrors(
            array(
              pht('You have not signed this document yet.'),
            ));
      }
    } else {
      $has_signed = true;
      $signature_data = $signature->getSignatureData();

      // In this case, we know they've signed.
      $signed_at = $signature->getDateCreated();
      $signed_status = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setErrors(
          array(
            pht(
              'You signed this document on %s.',
              phabricator_datetime($signed_at, $viewer)),
          ));
    }

    $e_name = true;
    $e_email = true;
    $e_agree = null;

    $errors = array();
    if ($request->isFormPost() && !$has_signed) {
      $name = $request->getStr('name');
      $agree = $request->getExists('agree');

      if (!strlen($name)) {
        $e_name = pht('Required');
        $errors[] = pht('Name field is required.');
      } else {
        $e_name = null;
      }
      $signature_data['name'] = $name;

      if ($viewer->isLoggedIn()) {
        $email = $viewer->loadPrimaryEmailAddress();
      } else {
        $email = $request->getStr('email');

        $addr_obj = null;
        if (!strlen($email)) {
          $e_email = pht('Required');
          $errors[] = pht('Email field is required.');
        } else {
          $addr_obj = new PhutilEmailAddress($email);
          $domain = $addr_obj->getDomainName();
          if (!$domain) {
            $e_email = pht('Invalid');
            $errors[] = pht('A valid email is required.');
          } else {
            $e_email = null;
          }
        }
      }
      $signature_data['email'] = $email;

      $signature->setSignatureData($signature_data);

      if (!$agree) {
        $errors[] = pht(
          'You must check "I agree to the terms laid forth above."');
        $e_agree = pht('Required');
      }

      if ($viewer->isLoggedIn()) {
        $verified = LegalpadDocumentSignature::VERIFIED;
      } else {
        $verified = LegalpadDocumentSignature::UNVERIFIED;
      }
      $signature->setVerified($verified);

      if (!$errors) {
        $signature->save();

        // If the viewer is logged in, send them to the document page, which
        // will show that they have signed the document. Otherwise, send them
        // to a completion page.
        if ($viewer->isLoggedIn()) {
          $next_uri = '/'.$document->getMonogram();
        } else {
          $next_uri = $this->getApplicationURI('done/');
        }

        return id(new AphrontRedirectResponse())->setURI($next_uri);
      }
    }

    $document_body = $document->getDocumentBody();
    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);
    $engine->addObject(
      $document_body,
      LegalpadDocumentBody::MARKUP_FIELD_TEXT);
    $engine->process();

    $document_markup = $engine->getOutput(
      $document_body,
      LegalpadDocumentBody::MARKUP_FIELD_TEXT);

    $title = $document_body->getTitle();

    $manage_uri = $this->getApplicationURI('view/'.$document->getID().'/');

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $document,
      PhabricatorPolicyCapability::CAN_EDIT);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-pencil'))
          ->setText(pht('Manage Document'))
          ->setHref($manage_uri)
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));

    $content = id(new PHUIDocumentView())
      ->addClass('legalpad')
      ->setHeader($header)
      ->setFontKit(PHUIDocumentView::FONT_SOURCE_SANS)
      ->appendChild(
        array(
          $signed_status,
          $document_markup,
        ));

    if (!$has_signed) {
      $error_view = null;
      if ($errors) {
        $error_view = id(new AphrontErrorView())
          ->setErrors($errors);
      }

      $signature_form = $this->buildSignatureForm(
        $document_body,
        $signature,
        $e_name,
        $e_email,
        $e_agree);

      $subheader = id(new PHUIHeaderView())
        ->setHeader(pht('Agree and Sign Document'))
        ->setBleedHeader(true);

      $content->appendChild(
        array(
          $subheader,
          $error_view,
          $signature_form,
        ));
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($document->getMonogram());

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
      array(
        'title' => $title,
        'pageObjects' => array($document->getPHID()),
      ));
  }

  private function buildSignatureForm(
    LegalpadDocumentBody $body,
    LegalpadDocumentSignature $signature,
    $e_name,
    $e_email,
    $e_agree) {

    $viewer = $this->getRequest()->getUser();
    $data = $signature->getSignatureData();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Name'))
        ->setValue(idx($data, 'name', ''))
        ->setName('name')
        ->setError($e_name));

    if (!$viewer->isLoggedIn()) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Email'))
          ->setValue(idx($data, 'email', ''))
          ->setName('email')
          ->setError($e_email));
    }

    $form
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setError($e_agree)
          ->addCheckbox(
            'agree',
            'agree',
            pht('I agree to the terms laid forth above.'),
            false))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Sign Document'))
          ->addCancelButton($this->getApplicationURI()));

    return $form;
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

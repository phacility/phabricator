<?php

final class LegalpadDocumentSignController extends LegalpadController {

  public function shouldAllowPublic() {
    return true;
  }

  public function shouldAllowLegallyNonCompliantUsers() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $document = id(new LegalpadDocumentQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->needDocumentBodies(true)
      ->executeOne();
    if (!$document) {
      return new Aphront404Response();
    }

    $information = $this->readSignerInformation(
      $document,
      $request);
    if ($information instanceof AphrontResponse) {
      return $information;
    }
    list($signer_phid, $signature_data) = $information;

    $signature = null;

    $type_individual = LegalpadDocument::SIGNATURE_TYPE_INDIVIDUAL;
    $is_individual = ($document->getSignatureType() == $type_individual);
    switch ($document->getSignatureType()) {
      case LegalpadDocument::SIGNATURE_TYPE_NONE:
        // nothing to sign means this should be true
        $has_signed = true;
        // this is a status UI element
        $signed_status = null;
        break;
      case LegalpadDocument::SIGNATURE_TYPE_INDIVIDUAL:
        if ($signer_phid) {
          // TODO: This is odd and should probably be adjusted after
          // grey/external accounts work better, but use the omnipotent
          // viewer to check for a signature so we can pick up
          // anonymous/grey signatures.

          $signature = id(new LegalpadDocumentSignatureQuery())
            ->setViewer(PhabricatorUser::getOmnipotentUser())
            ->withDocumentPHIDs(array($document->getPHID()))
            ->withSignerPHIDs(array($signer_phid))
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
            ->setDocumentVersion($document->getVersions());

          // If the user is logged in, show a notice that they haven't signed.
          // If they aren't logged in, we can't be as sure, so don't show
          // anything.
          if ($viewer->isLoggedIn()) {
            $signed_status = id(new PHUIInfoView())
              ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
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

          if ($signature->getIsExemption()) {
            $exemption_phid = $signature->getExemptionPHID();
            $handles = $this->loadViewerHandles(array($exemption_phid));
            $exemption_handle = $handles[$exemption_phid];

            $signed_text = pht(
              'You do not need to sign this document. '.
              '%s added a signature exemption for you on %s.',
              $exemption_handle->renderLink(),
              phabricator_datetime($signed_at, $viewer));
          } else {
            $signed_text = pht(
              'You signed this document on %s.',
              phabricator_datetime($signed_at, $viewer));
          }

          $signed_status = id(new PHUIInfoView())
            ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
            ->setErrors(array($signed_text));
        }

        $field_errors = array(
          'name' => true,
          'email' => true,
          'agree' => true,
        );
        $signature->setSignatureData($signature_data);
        break;

      case LegalpadDocument::SIGNATURE_TYPE_CORPORATION:
        $signature = id(new LegalpadDocumentSignature())
          ->setDocumentPHID($document->getPHID())
          ->setDocumentVersion($document->getVersions());

        if ($viewer->isLoggedIn()) {
          $has_signed = false;

          $signed_status = null;
        } else {
          // This just hides the form.
          $has_signed = true;

          $login_text = pht(
            'This document requires a corporate signatory. You must log in to '.
            'accept this document on behalf of a company you represent.');
          $signed_status = id(new PHUIInfoView())
            ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
            ->setErrors(array($login_text));
        }

        $field_errors = array(
          'name' => true,
          'address' => true,
          'contact.name' => true,
          'email' => true,
        );
        $signature->setSignatureData($signature_data);
        break;
    }

    $errors = array();
    if ($request->isFormOrHisecPost() && !$has_signed) {

      // Require two-factor auth to sign legal documents.
      if ($viewer->isLoggedIn()) {
        $engine = new PhabricatorAuthSessionEngine();
        $engine->requireHighSecuritySession(
          $viewer,
          $request,
          '/'.$document->getMonogram());
      }

      list($form_data, $errors, $field_errors) = $this->readSignatureForm(
        $document,
        $request);

      $signature_data = $form_data + $signature_data;

      $signature->setSignatureData($signature_data);
      $signature->setSignatureType($document->getSignatureType());
      $signature->setSignerName((string)idx($signature_data, 'name'));
      $signature->setSignerEmail((string)idx($signature_data, 'email'));

      $agree = $request->getExists('agree');
      if (!$agree) {
        $errors[] = pht(
          'You must check "I agree to the terms laid forth above."');
        $field_errors['agree'] = pht('Required');
      }

      if ($viewer->isLoggedIn() && $is_individual) {
        $verified = LegalpadDocumentSignature::VERIFIED;
      } else {
        $verified = LegalpadDocumentSignature::UNVERIFIED;
      }
      $signature->setVerified($verified);

      if (!$errors) {
        $signature->save();

        // If the viewer is logged in, signing for themselves, send them to
        // the document page, which will show that they have signed the
        // document. Unless of course they were required to sign the
        // document to use Phabricator; in that case try really hard to
        // re-direct them to where they wanted to go.
        //
        // Otherwise, send them to a completion page.
        if ($viewer->isLoggedIn() && $is_individual) {
          $next_uri = '/'.$document->getMonogram();
          if ($document->getRequireSignature()) {
            $request_uri = $request->getRequestURI();
            $next_uri = (string)$request_uri;
          }
        } else {
          $this->sendVerifySignatureEmail(
            $document,
            $signature);

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

    // Use the last content update as the modified date. We don't want to
    // show that a document like a TOS was "updated" by an incidental change
    // to a field like the preamble or privacy settings which does not acutally
    // affect the content of the agreement.
    $content_updated = $document_body->getDateCreated();

    // NOTE: We're avoiding `setPolicyObject()` here so we don't pick up
    // extra UI elements that are unnecessary and clutter the signature page.
    // These details are available on the "Manage" page.
    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setEpoch($content_updated)
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setIcon('fa-pencil')
          ->setText(pht('Manage'))
          ->setHref($manage_uri)
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));

    $preamble_box = null;
    if (strlen($document->getPreamble())) {
      $preamble_text = new PHUIRemarkupView($viewer, $document->getPreamble());

      // NOTE: We're avoiding `setObject()` here so we don't pick up extra UI
      // elements like "Subscribers". This information is available on the
      // "Manage" page, but just clutters up the "Signature" page.
      $preamble = id(new PHUIPropertyListView())
        ->setUser($viewer)
        ->addSectionHeader(pht('Preamble'))
        ->addTextContent($preamble_text);

      $preamble_box = new PHUIPropertyGroupView();
      $preamble_box->addPropertyList($preamble);
    }

    $content = id(new PHUIDocumentViewPro())
      ->addClass('legalpad')
      ->setHeader($header)
      ->appendChild(
        array(
          $signed_status,
          $preamble_box,
          $document_markup,
        ));

    $signature_box = null;
    if (!$has_signed) {
      $error_view = null;
      if ($errors) {
        $error_view = id(new PHUIInfoView())
          ->setErrors($errors);
      }

      $signature_form = $this->buildSignatureForm(
        $document,
        $signature,
        $field_errors);

      switch ($document->getSignatureType()) {
        default:
          break;
        case LegalpadDocument::SIGNATURE_TYPE_INDIVIDUAL:
        case LegalpadDocument::SIGNATURE_TYPE_CORPORATION:
          $box = id(new PHUIObjectBoxView())
            ->addClass('document-sign-box')
            ->setHeaderText(pht('Agree and Sign Document'))
            ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
            ->setForm($signature_form);
          if ($error_view) {
            $box->setInfoView($error_view);
          }
          $signature_box = phutil_tag_div(
            'phui-document-view-pro-box plt', $box);
          break;
      }


    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);
    $crumbs->addTextCrumb($document->getMonogram());

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($document->getPHID()))
      ->appendChild(array(
        $content,
        $signature_box,
      ));
  }

  private function readSignerInformation(
    LegalpadDocument $document,
    AphrontRequest $request) {

    $viewer = $request->getUser();
    $signer_phid = null;
    $signature_data = array();

    switch ($document->getSignatureType()) {
      case LegalpadDocument::SIGNATURE_TYPE_NONE:
        break;
      case LegalpadDocument::SIGNATURE_TYPE_INDIVIDUAL:
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
        break;
      case LegalpadDocument::SIGNATURE_TYPE_CORPORATION:
        $signer_phid = $viewer->getPHID();
        if ($signer_phid) {
          $signature_data = array(
            'contact.name' => $viewer->getRealName(),
            'email' => $viewer->loadPrimaryEmailAddress(),
            'actorPHID' => $viewer->getPHID(),
          );
        }
        break;
    }

    return array($signer_phid, $signature_data);
  }

  private function buildSignatureForm(
    LegalpadDocument $document,
    LegalpadDocumentSignature $signature,
    array $errors) {

    $viewer = $this->getRequest()->getUser();
    $data = $signature->getSignatureData();

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $signature_type = $document->getSignatureType();
    switch ($signature_type) {
      case LegalpadDocument::SIGNATURE_TYPE_NONE:
        // bail out of here quick
        return;
      case LegalpadDocument::SIGNATURE_TYPE_INDIVIDUAL:
        $this->buildIndividualSignatureForm(
          $form,
          $document,
          $signature,
          $errors);
        break;
      case LegalpadDocument::SIGNATURE_TYPE_CORPORATION:
        $this->buildCorporateSignatureForm(
          $form,
          $document,
          $signature,
          $errors);
        break;
      default:
        throw new Exception(
          pht(
            'This document has an unknown signature type ("%s").',
            $signature_type));
    }

    $form
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setError(idx($errors, 'agree', null))
          ->addCheckbox(
            'agree',
            'agree',
            pht('I agree to the terms laid forth above.'),
            false));
    if ($document->getRequireSignature()) {
      $cancel_uri = '/logout/';
      $cancel_text = pht('Log Out');
    } else {
      $cancel_uri = $this->getApplicationURI();
      $cancel_text = pht('Cancel');
    }
    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Sign Document'))
          ->addCancelButton($cancel_uri, $cancel_text));

    return $form;
  }

  private function buildIndividualSignatureForm(
    AphrontFormView $form,
    LegalpadDocument $document,
    LegalpadDocumentSignature $signature,
    array $errors) {

    $data = $signature->getSignatureData();

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Name'))
        ->setValue(idx($data, 'name', ''))
        ->setName('name')
        ->setError(idx($errors, 'name', null)));

    $viewer = $this->getRequest()->getUser();
    if (!$viewer->isLoggedIn()) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Email'))
          ->setValue(idx($data, 'email', ''))
          ->setName('email')
          ->setError(idx($errors, 'email', null)));
    }

    return $form;
  }

  private function buildCorporateSignatureForm(
    AphrontFormView $form,
    LegalpadDocument $document,
    LegalpadDocumentSignature $signature,
    array $errors) {

    $data = $signature->getSignatureData();

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Company Name'))
        ->setValue(idx($data, 'name', ''))
        ->setName('name')
        ->setError(idx($errors, 'name', null)))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
        ->setLabel(pht('Company Address'))
        ->setValue(idx($data, 'address', ''))
        ->setName('address')
        ->setError(idx($errors, 'address', null)))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Contact Name'))
        ->setValue(idx($data, 'contact.name', ''))
        ->setName('contact.name')
        ->setError(idx($errors, 'contact.name', null)))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Contact Email'))
        ->setValue(idx($data, 'email', ''))
        ->setName('email')
        ->setError(idx($errors, 'email', null)));

    return $form;
  }

  private function readSignatureForm(
    LegalpadDocument $document,
    AphrontRequest $request) {

    $signature_type = $document->getSignatureType();
    switch ($signature_type) {
      case LegalpadDocument::SIGNATURE_TYPE_INDIVIDUAL:
        $result = $this->readIndividualSignatureForm(
          $document,
          $request);
        break;
      case LegalpadDocument::SIGNATURE_TYPE_CORPORATION:
        $result = $this->readCorporateSignatureForm(
          $document,
          $request);
        break;
      default:
        throw new Exception(
          pht(
            'This document has an unknown signature type ("%s").',
            $signature_type));
    }

    return $result;
  }

  private function readIndividualSignatureForm(
    LegalpadDocument $document,
    AphrontRequest $request) {

    $signature_data = array();
    $errors = array();
    $field_errors = array();


    $name = $request->getStr('name');

    if (!strlen($name)) {
      $field_errors['name'] = pht('Required');
      $errors[] = pht('Name field is required.');
    } else {
      $field_errors['name'] = null;
    }
    $signature_data['name'] = $name;

    $viewer = $request->getUser();
    if ($viewer->isLoggedIn()) {
      $email = $viewer->loadPrimaryEmailAddress();
    } else {
      $email = $request->getStr('email');

      $addr_obj = null;
      if (!strlen($email)) {
        $field_errors['email'] = pht('Required');
        $errors[] = pht('Email field is required.');
      } else {
        $addr_obj = new PhutilEmailAddress($email);
        $domain = $addr_obj->getDomainName();
        if (!$domain) {
          $field_errors['email'] = pht('Invalid');
          $errors[] = pht('A valid email is required.');
        } else {
          $field_errors['email'] = null;
        }
      }
    }
    $signature_data['email'] = $email;

    return array($signature_data, $errors, $field_errors);
  }

  private function readCorporateSignatureForm(
    LegalpadDocument $document,
    AphrontRequest $request) {

    $viewer = $request->getUser();
    if (!$viewer->isLoggedIn()) {
      throw new Exception(
        pht(
          'You can not sign a document on behalf of a corporation unless '.
          'you are logged in.'));
    }

    $signature_data = array();
    $errors = array();
    $field_errors = array();

    $name = $request->getStr('name');

    if (!strlen($name)) {
      $field_errors['name'] = pht('Required');
      $errors[] = pht('Company name is required.');
    } else {
      $field_errors['name'] = null;
    }
    $signature_data['name'] = $name;

    $address = $request->getStr('address');
    if (!strlen($address)) {
      $field_errors['address'] = pht('Required');
      $errors[] = pht('Company address is required.');
    } else {
      $field_errors['address'] = null;
    }
    $signature_data['address'] = $address;

    $contact_name = $request->getStr('contact.name');
    if (!strlen($contact_name)) {
      $field_errors['contact.name'] = pht('Required');
      $errors[] = pht('Contact name is required.');
    } else {
      $field_errors['contact.name'] = null;
    }
    $signature_data['contact.name'] = $contact_name;

    $email = $request->getStr('email');
    $addr_obj = null;
    if (!strlen($email)) {
      $field_errors['email'] = pht('Required');
      $errors[] = pht('Contact email is required.');
    } else {
      $addr_obj = new PhutilEmailAddress($email);
      $domain = $addr_obj->getDomainName();
      if (!$domain) {
        $field_errors['email'] = pht('Invalid');
        $errors[] = pht('A valid email is required.');
      } else {
        $field_errors['email'] = null;
      }
    }
    $signature_data['email'] = $email;

    return array($signature_data, $errors, $field_errors);
  }

  private function sendVerifySignatureEmail(
    LegalpadDocument $doc,
    LegalpadDocumentSignature $signature) {

    $signature_data = $signature->getSignatureData();
    $email = new PhutilEmailAddress($signature_data['email']);
    $doc_name = $doc->getTitle();
    $doc_link = PhabricatorEnv::getProductionURI('/'.$doc->getMonogram());
    $path = $this->getApplicationURI(sprintf(
      '/verify/%s/',
      $signature->getSecretKey()));
    $link = PhabricatorEnv::getProductionURI($path);

    $name = idx($signature_data, 'name');

    $body = pht(
      "%s:\n\n".
      "This email address was used to sign a Legalpad document ".
      "in Phabricator:\n\n".
      "  %s\n\n".
      "Please verify you own this email address and accept the ".
      "agreement by clicking this link:\n\n".
      "  %s\n\n".
      "Your signature is not valid until you complete this ".
      "verification step.\n\nYou can review the document here:\n\n".
      "  %s\n",
      $name,
      $doc_name,
      $link,
      $doc_link);

    id(new PhabricatorMetaMTAMail())
      ->addRawTos(array($email->getAddress()))
      ->setSubject(pht('[Legalpad] Signature Verification'))
      ->setForceDelivery(true)
      ->setBody($body)
      ->setRelatedPHID($signature->getDocumentPHID())
      ->saveAndSend();
  }

  private function signInResponse() {
    return id(new Aphront403Response())
      ->setForbiddenText(
        pht(
          'The email address specified is associated with an account. '.
          'Please login to that account and sign this document again.'));
  }

}

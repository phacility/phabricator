<?php

/**
 * @group legalpad
 */
final class LegalpadDocumentSignController extends LegalpadController {

  private $id;

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

    $signature = id(new LegalpadDocumentSignature())
      ->loadOneWhere(
        'documentPHID = %s AND documentVersion = %d AND signerPHID = %s',
        $document->getPHID(),
        $document->getVersions(),
        $user->getPHID());

    if (!$signature) {
      $has_signed = false;
      $error_view = null;
      $signature = id(new LegalpadDocumentSignature())
        ->setSignerPHID($user->getPHID())
        ->setDocumentPHID($document->getPHID())
        ->setDocumentVersion($document->getVersions());
      $data = array(
        'name' => $user->getRealName(),
        'email' => $user->loadPrimaryEmailAddress());
      $signature->setSignatureData($data);
    } else {
      $has_signed = true;
      $error_view = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle(pht('You have already agreed to these terms.'));
      $data = $signature->getSignatureData();
    }

    $e_name = true;
    $e_email = true;
    $e_address_1 = true;
    $errors = array();
    if ($request->isFormPost()) {
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
      $data['name'] = $name;

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
      $data['email'] = $email;

      if (!$address_1) {
        $e_address_1 = pht('Required');
        $errors[] = pht('Address line 1 field is required.');
      }
      $data['address_1'] = $address_1;
      $data['address_2'] = $address_2;
      $data['phone'] = $phone;
      $signature->setSignatureData($data);

      if (!$agree) {
        $errors[] = pht(
          'You must check "I agree to the terms laid forth above."');
      }

      if (!$errors) {
        $signature->save();
        $has_signed = true;
        $error_view = id(new AphrontErrorView())
          ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
          ->setTitle(pht('Signature successful. Thank you.'));
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

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $content = array(
      id(new PHUIDocumentView())
      ->setHeader($header)
      ->appendChild($this->buildDocument($engine, $document_body)),
      $error_view,
      $this->buildSignatureForm(
        $document_body,
        $signature,
        $has_signed,
        $e_name,
        $e_email,
        $e_address_1));

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $title,
        'device' => true,
        'pageObjects' => array($document->getPHID()),
      ));
  }

  private function buildDocument(
    PhabricatorMarkupEngine
    $engine, LegalpadDocumentBody $body) {

    require_celerity_resource('legalpad-documentbody-css');

    return phutil_tag(
      'div',
      array(
        'class' => 'legalpad-documentbody'
      ),
      $engine->getOutput($body, LegalpadDocumentBody::MARKUP_FIELD_TEXT));

  }

  private function buildSignatureForm(
    LegalpadDocumentBody $body,
    LegalpadDocumentSignature $signature,
    $has_signed = false,
    $e_name = true,
    $e_email = true,
    $e_address_1 = true) {

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
        id(new AphrontFormInsetView())
        ->setTitle(pht('Sign and Agree'))
        ->setDescription($instructions)
        ->setContent(phutil_tag('br', array()))
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
          ->setDisabled($has_signed)));

    return $form;
  }

}

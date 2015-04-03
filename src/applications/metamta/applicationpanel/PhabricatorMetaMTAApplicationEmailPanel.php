<?php

final class PhabricatorMetaMTAApplicationEmailPanel
  extends PhabricatorApplicationConfigurationPanel {

  public function getPanelKey() {
    return 'email';
  }

  public function shouldShowForApplication(
    PhabricatorApplication $application) {
    return $application->supportsEmailIntegration();
  }

  public function buildConfigurationPagePanel() {
    $viewer = $this->getViewer();
    $application = $this->getApplication();

    $addresses = id(new PhabricatorMetaMTAApplicationEmailQuery())
      ->setViewer($viewer)
      ->withApplicationPHIDs(array($application->getPHID()))
      ->execute();

    $rows = array();
    foreach ($addresses as $address) {
      $rows[] = array(
        $address->getAddress(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No email addresses configured.'))
      ->setHeaders(
        array(
          pht('Address'),
        ));

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $application,
      PhabricatorPolicyCapability::CAN_EDIT);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Application Emails'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setText(pht('Edit Application Emails'))
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-pencil'))
          ->setHref($this->getPanelURI())
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));


    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($table);

    return $box;
  }

  public function handlePanelRequest(
    AphrontRequest $request,
    PhabricatorController $controller) {
    $viewer = $request->getViewer();
    $application = $this->getApplication();

    $path = $request->getURIData('path');
    if (strlen($path)) {
      return new Aphront404Response();
    }

    $uri = $request->getRequestURI();
    $uri->setQueryParams(array());

    $new = $request->getStr('new');
    $edit = $request->getInt('edit');
    $delete = $request->getInt('delete');

    if ($new) {
      return $this->returnNewAddressResponse($request, $uri, $application);
    }

    if ($edit) {
      return $this->returnEditAddressResponse($request, $uri, $edit);
    }

    if ($delete) {
      return $this->returnDeleteAddressResponse($request, $uri, $delete);
    }

    $emails = id(new PhabricatorMetaMTAApplicationEmailQuery())
      ->setViewer($viewer)
      ->withApplicationPHIDs(array($application->getPHID()))
      ->execute();

    $highlight = $request->getInt('highlight');
    $rowc = array();
    $rows = array();
    foreach ($emails as $email) {

      $button_edit = javelin_tag(
        'a',
        array(
          'class' => 'button small grey',
          'href'  => $uri->alter('edit', $email->getID()),
          'sigil' => 'workflow',
        ),
        pht('Edit'));

      $button_remove = javelin_tag(
        'a',
        array(
          'class'   => 'button small grey',
          'href'    => $uri->alter('delete', $email->getID()),
          'sigil'   => 'workflow',
        ),
        pht('Delete'));

      if ($highlight == $email->getID()) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = null;
      }

      $rows[] = array(
        $email->getAddress(),
        $button_edit,
        $button_remove,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No application emails created yet.'));
    $table->setHeaders(
      array(
        pht('Email'),
        pht('Edit'),
        pht('Delete'),
      ));
    $table->setColumnClasses(
      array(
        'wide',
        'action',
        'action',
      ));
    $table->setRowClasses($rowc);
    $table->setColumnVisibility(
      array(
        true,
        true,
        true,
      ));
    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $crumbs = $controller->buildPanelCrumbs($this);
    $crumbs->addTextCrumb(pht('Edit Application Emails'));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Edit Application Emails: %s', $application->getName()))
      ->setSubheader($application->getAppEmailBlurb());

    $icon = id(new PHUIIconView())
      ->setIconFont('fa-plus');
    $button = new PHUIButtonView();
    $button->setText(pht('Add New Address'));
    $button->setTag('a');
    $button->setHref($uri->alter('new', 'true'));
    $button->setIcon($icon);
    $button->addSigil('workflow');
    $header->addActionLink($button);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($table);

    $title = $application->getName();

    return $controller->buildPanelPage(
      $this,
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
      ));
  }

  private function validateApplicationEmail($email) {
    $errors = array();
    $e_email = true;

    if (!strlen($email)) {
      $e_email = pht('Required');
      $errors[] = pht('Email is required.');
    } else if (!PhabricatorUserEmail::isValidAddress($email)) {
      $e_email = pht('Invalid');
      $errors[] = PhabricatorUserEmail::describeValidAddresses();
    } else if (!PhabricatorUserEmail::isAllowedAddress($email)) {
      $e_email = pht('Disallowed');
      $errors[] = PhabricatorUserEmail::describeAllowedAddresses();
    }
    $user_emails = id(new PhabricatorUserEmail())
      ->loadAllWhere('address = %s', $email);
    if ($user_emails) {
      $e_email = pht('Duplicate');
      $errors[] = pht('A user already has this email.');
    }

    return array($e_email, $errors);
  }

  private function returnNewAddressResponse(
    AphrontRequest $request,
    PhutilURI $uri,
    PhabricatorApplication $application) {

    $viewer = $request->getUser();
    $email_object =
      PhabricatorMetaMTAApplicationEmail::initializeNewAppEmail($viewer)
      ->setApplicationPHID($application->getPHID());

    return $this->returnSaveAddressResponse(
      $request,
      $uri,
      $email_object,
      $is_new = true);
  }

  private function returnEditAddressResponse(
    AphrontRequest $request,
    PhutilURI $uri,
    $email_object_id) {

    $viewer = $request->getUser();
    $email_object = id(new PhabricatorMetaMTAApplicationEmailQuery())
      ->setViewer($viewer)
      ->withIDs(array($email_object_id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$email_object) {
      return new Aphront404Response();
    }

    return $this->returnSaveAddressResponse(
      $request,
      $uri,
      $email_object,
      $is_new = false);
  }

  private function returnSaveAddressResponse(
    AphrontRequest $request,
    PhutilURI $uri,
    PhabricatorMetaMTAApplicationEmail $email_object,
    $is_new) {

    $viewer = $request->getUser();

    $e_email = true;
    $email   = null;
    $errors  = array();
    $default_user_key =
      PhabricatorMetaMTAApplicationEmail::CONFIG_DEFAULT_AUTHOR;
    if ($request->isDialogFormPost()) {
      $email = trim($request->getStr('email'));
      list($e_email, $errors) = $this->validateApplicationEmail($email);
      $email_object->setAddress($email);
      $default_user = $request->getArr($default_user_key);
      $default_user = reset($default_user);
      if ($default_user) {
        $email_object->setConfigValue($default_user_key, $default_user);
      }

      if (!$errors) {
        try {
          $email_object->save();
          return id(new AphrontRedirectResponse())->setURI(
            $uri->alter('highlight', $email_object->getID()));
        } catch (AphrontDuplicateKeyQueryException $ex) {
          $e_email = pht('Duplicate');
          $errors[] = pht(
            'Another application is already configured to use this email '.
            'address.');
        }
      }
    }

    if ($errors) {
      $errors = id(new PHUIInfoView())
        ->setErrors($errors);
    }

    $default_user = $email_object->getConfigValue($default_user_key);
    if ($default_user) {
      $default_user_value = array($default_user);
    } else {
      $default_user_value = array();
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Email'))
          ->setName('email')
          ->setValue($email_object->getAddress())
          ->setCaption(PhabricatorUserEmail::describeAllowedAddresses())
          ->setError($e_email))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setLabel(pht('Default Author'))
          ->setName($default_user_key)
          ->setLimit(1)
          ->setValue($default_user_value)
          ->setCaption(pht(
            'Used if the "From:" address does not map to a known account.')));
    if ($is_new) {
      $title = pht('New Address');
    } else {
      $title = pht('Edit Address');
    }
    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle($title)
      ->appendChild($errors)
      ->appendForm($form)
      ->addSubmitButton(pht('Save'))
      ->addCancelButton($uri);

    if ($is_new) {
      $dialog->addHiddenInput('new', 'true');
    }

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function returnDeleteAddressResponse(
    AphrontRequest $request,
    PhutilURI $uri,
    $email_object_id) {

    $viewer = $request->getUser();
    $email_object = id(new PhabricatorMetaMTAApplicationEmailQuery())
      ->setViewer($viewer)
      ->withIDs(array($email_object_id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$email_object) {
      return new Aphront404Response();
    }

    if ($request->isDialogFormPost()) {
      $email_object->delete();
      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->addHiddenInput('delete', $email_object_id)
      ->setTitle(pht('Delete Address'))
      ->appendParagraph(pht(
        'Are you sure you want to delete this email address?'))
      ->addSubmitButton(pht('Delete'))
      ->addCancelButton($uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}

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

    $table = $this->buildEmailTable($is_edit = false, null);

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
          ->setIcon('fa-pencil')
          ->setHref($this->getPanelURI())
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));


    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);

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

    $table = $this->buildEmailTable(
      $is_edit = true,
      $request->getInt('id'));

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $crumbs = $controller->buildPanelCrumbs($this);
    $crumbs->addTextCrumb(pht('Edit Application Emails'));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Edit Application Emails: %s', $application->getName()))
      ->setSubheader($application->getAppEmailBlurb())
      ->setHeaderIcon('fa-pencil');

    $icon = id(new PHUIIconView())
      ->setIcon('fa-plus');
    $button = new PHUIButtonView();
    $button->setText(pht('Add New Address'));
    $button->setTag('a');
    $button->setHref($uri->alter('new', 'true'));
    $button->setIcon($icon);
    $button->addSigil('workflow');
    $header->addActionLink($button);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Emails'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);

    $title = $application->getName();
    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($object_box);

    return $controller->buildPanelPage(
      $this,
      $title,
      $crumbs,
      $view);
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

    $config_default =
      PhabricatorMetaMTAApplicationEmail::CONFIG_DEFAULT_AUTHOR;

    $e_email = true;
    $v_email = $email_object->getAddress();
    $e_space = null;
    $v_space = $email_object->getSpacePHID();
    $v_default = $email_object->getConfigValue($config_default);

    $validation_exception = null;
    if ($request->isDialogFormPost()) {
      $e_email = null;

      $v_email = trim($request->getStr('email'));
      $v_space = $request->getStr('spacePHID');
      $v_default = $request->getArr($config_default);
      $v_default = nonempty(head($v_default), null);

      $type_address =
        PhabricatorMetaMTAApplicationEmailTransaction::TYPE_ADDRESS;
      $type_space = PhabricatorTransactions::TYPE_SPACE;
      $type_config =
        PhabricatorMetaMTAApplicationEmailTransaction::TYPE_CONFIG;

      $key_config = PhabricatorMetaMTAApplicationEmailTransaction::KEY_CONFIG;

      $xactions = array();

      $xactions[] = id(new PhabricatorMetaMTAApplicationEmailTransaction())
        ->setTransactionType($type_address)
        ->setNewValue($v_email);

      $xactions[] = id(new PhabricatorMetaMTAApplicationEmailTransaction())
        ->setTransactionType($type_space)
        ->setNewValue($v_space);

      $xactions[] = id(new PhabricatorMetaMTAApplicationEmailTransaction())
        ->setTransactionType($type_config)
        ->setMetadataValue($key_config, $config_default)
        ->setNewValue($v_default);

      $editor = id(new PhabricatorMetaMTAApplicationEmailEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($email_object, $xactions);

        return id(new AphrontRedirectResponse())->setURI(
          $uri->alter('highlight', $email_object->getID()));
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_email = $ex->getShortMessage($type_address);
        $e_space = $ex->getShortMessage($type_space);
      }
    }

    if ($v_default) {
      $v_default = array($v_default);
    } else {
      $v_default = array();
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Email'))
          ->setName('email')
          ->setValue($v_email)
          ->setError($e_email));

    if (PhabricatorSpacesNamespaceQuery::getViewerSpacesExist($viewer)) {
      $form->appendControl(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Space'))
          ->setName('spacePHID')
          ->setValue($v_space)
          ->setError($e_space)
          ->setOptions(
            PhabricatorSpacesNamespaceQuery::getSpaceOptionsForViewer(
              $viewer,
              $v_space)));
    }

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setLabel(pht('Default Author'))
          ->setName($config_default)
          ->setLimit(1)
          ->setValue($v_default)
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
      ->setValidationException($validation_exception)
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

    $viewer = $this->getViewer();

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
      $engine = new PhabricatorDestructionEngine();
      $engine->destroyObject($email_object);
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

  private function buildEmailTable($is_edit, $highlight) {
    $viewer = $this->getViewer();
    $application = $this->getApplication();
    $uri = new PhutilURI($this->getPanelURI());

    $emails = id(new PhabricatorMetaMTAApplicationEmailQuery())
      ->setViewer($viewer)
      ->withApplicationPHIDs(array($application->getPHID()))
      ->execute();

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

      $space_phid = PhabricatorSpacesNamespaceQuery::getObjectSpacePHID($email);
      if ($space_phid) {
        $email_space = $viewer->renderHandle($space_phid);
      } else {
        $email_space = null;
      }

      $rows[] = array(
        $email_space,
        $email->getAddress(),
        $button_edit,
        $button_remove,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No application emails created yet.'));
    $table->setHeaders(
      array(
        pht('Space'),
        pht('Email'),
        pht('Edit'),
        pht('Delete'),
      ));
    $table->setColumnClasses(
      array(
        '',
        'wide',
        'action',
        'action',
      ));
    $table->setRowClasses($rowc);
    $table->setColumnVisibility(
      array(
        PhabricatorSpacesNamespaceQuery::getViewerSpacesExist($viewer),
        true,
        $is_edit,
        $is_edit,
      ));

    return $table;
  }

}

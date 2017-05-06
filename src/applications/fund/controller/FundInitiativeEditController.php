<?php

final class FundInitiativeEditController
  extends FundController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $initiative = id(new FundInitiativeQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$initiative) {
        return new Aphront404Response();
      }
      $is_new = false;
    } else {
      $initiative = FundInitiative::initializeNewInitiative($viewer);
      $is_new = true;
    }

    if ($is_new) {
      $title = pht('Create Initiative');
      $button_text = pht('Create Initiative');
      $cancel_uri = $this->getApplicationURI();
      $header_icon = 'fa-plus-square';
    } else {
      $title = pht(
        'Edit Initiative: %s',
        $initiative->getName());
      $button_text = pht('Save Changes');
      $cancel_uri = '/'.$initiative->getMonogram();
      $header_icon = 'fa-pencil';
    }

    $e_name = true;
    $v_name = $initiative->getName();

    $e_merchant = null;
    $v_merchant = $initiative->getMerchantPHID();

    $v_desc = $initiative->getDescription();
    $v_risk = $initiative->getRisks();

    if ($is_new) {
      $v_projects = array();
    } else {
      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $initiative->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
    }

    $validation_exception = null;
    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_desc = $request->getStr('description');
      $v_risk = $request->getStr('risks');
      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');
      $v_merchant = $request->getStr('merchantPHID');
      $v_projects = $request->getArr('projects');

      $type_name = FundInitiativeNameTransaction::TRANSACTIONTYPE;
      $type_desc = FundInitiativeDescriptionTransaction::TRANSACTIONTYPE;
      $type_risk = FundInitiativeRisksTransaction::TRANSACTIONTYPE;
      $type_merchant = FundInitiativeMerchantTransaction::TRANSACTIONTYPE;
      $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
      $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;

      $xactions = array();

      $xactions[] = id(new FundInitiativeTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new FundInitiativeTransaction())
        ->setTransactionType($type_desc)
        ->setNewValue($v_desc);

      $xactions[] = id(new FundInitiativeTransaction())
        ->setTransactionType($type_risk)
        ->setNewValue($v_risk);

      $xactions[] = id(new FundInitiativeTransaction())
        ->setTransactionType($type_merchant)
        ->setNewValue($v_merchant);

      $xactions[] = id(new FundInitiativeTransaction())
        ->setTransactionType($type_view)
        ->setNewValue($v_view);

      $xactions[] = id(new FundInitiativeTransaction())
        ->setTransactionType($type_edit)
        ->setNewValue($v_edit);

      $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
      $xactions[] = id(new FundInitiativeTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $proj_edge_type)
        ->setNewValue(array('=' => array_fuse($v_projects)));

      $editor = id(new FundInitiativeEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($initiative, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI('/'.$initiative->getMonogram());
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_name = $ex->getShortMessage($type_name);
        $e_merchant = $ex->getShortMessage($type_merchant);

        $initiative->setViewPolicy($v_view);
        $initiative->setEditPolicy($v_edit);
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($initiative)
      ->execute();

    $merchants = id(new PhortuneMerchantQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    $merchant_options = array();
    foreach ($merchants as $merchant) {
      $merchant_options[$merchant->getPHID()] = pht(
        'Merchant %d %s',
        $merchant->getID(),
        $merchant->getName());
    }

    if ($v_merchant && empty($merchant_options[$v_merchant])) {
      $merchant_options = array(
        $v_merchant => pht('(Restricted Merchant)'),
      ) + $merchant_options;
    }

    if (!$merchant_options) {
      return $this->newDialog()
        ->setTitle(pht('No Valid Phortune Merchant Accounts'))
        ->appendParagraph(
          pht(
            'You do not control any merchant accounts which can receive '.
            'payments from this initiative. When you create an initiative, '.
            'you need to specify a merchant account where funds will be paid '.
            'to.'))
        ->appendParagraph(
          pht(
            'Create a merchant account in the Phortune application before '.
            'creating an initiative in Fund.'))
        ->addCancelButton($this->getApplicationURI());
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('merchantPHID')
          ->setLabel(pht('Pay To Merchant'))
          ->setValue($v_merchant)
          ->setError($e_merchant)
          ->setOptions($merchant_options))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setName('description')
          ->setLabel(pht('Description'))
          ->setValue($v_desc))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setName('risks')
          ->setLabel(pht('Risks/Challenges'))
          ->setValue($v_risk))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Tags'))
          ->setName('projects')
          ->setValue($v_projects)
          ->setDatasource(new PhabricatorProjectDatasource()))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($initiative)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($initiative)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($button_text)
          ->addCancelButton($cancel_uri));

    $crumbs = $this->buildApplicationCrumbs();
    if ($is_new) {
      $crumbs->addTextCrumb(pht('Create Initiative'));
    } else {
      $crumbs->addTextCrumb(
        $initiative->getMonogram(),
        '/'.$initiative->getMonogram());
      $crumbs->addTextCrumb(pht('Edit'));
    }
    $crumbs->setBorder(true);

    $box = id(new PHUIObjectBoxView())
      ->setValidationException($validation_exception)
      ->setHeaderText(pht('Initiative'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($form);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon($header_icon);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($box);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}

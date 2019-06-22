<?php

final class PhabricatorEditEngineConfigurationSortController
  extends PhabricatorEditEngineController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $engine_key = $request->getURIData('engineKey');
    $this->setEngineKey($engine_key);

    $type = $request->getURIData('type');
    $is_create = ($type == 'create');

    $engine = id(new PhabricatorEditEngineQuery())
      ->setViewer($viewer)
      ->withEngineKeys(array($engine_key))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$engine) {
      return id(new Aphront404Response());
    }

    $cancel_uri = "/transactions/editengine/{$engine_key}/";
    $reorder_uri = "/transactions/editengine/{$engine_key}/sort/{$type}/";

    $query = id(new PhabricatorEditEngineConfigurationQuery())
      ->setViewer($viewer)
      ->withEngineKeys(array($engine->getEngineKey()));

    if ($is_create) {
      $query->withIsDefault(true);
    } else {
      $query->withIsEdit(true);
    }

    $configs = $query->execute();

    // Do this check here (instead of in the Query above) to get a proper
    // policy exception if the user doesn't satisfy
    foreach ($configs as $config) {
      PhabricatorPolicyFilter::requireCapability(
        $viewer,
        $config,
        PhabricatorPolicyCapability::CAN_EDIT);
    }

    if ($is_create) {
      $configs = msort($configs, 'getCreateSortKey');
    } else {
      $configs = msort($configs, 'getEditSortKey');
    }

    if ($request->isFormPost()) {
      $form_order = $request->getStrList('formOrder');

      // NOTE: This has a side-effect of saving any factory-default forms
      // to the database. We might want to warn the user better, but this
      // shouldn't generally be very important or confusing.

      $configs = mpull($configs, null, 'getIdentifier');
      $configs = array_select_keys($configs, $form_order) + $configs;

      $order = 1;
      foreach ($configs as $config) {
        $xactions = array();

        if ($is_create) {
          $xaction_type =
            PhabricatorEditEngineCreateOrderTransaction::TRANSACTIONTYPE;
        } else {
          $xaction_type =
            PhabricatorEditEngineEditOrderTransaction::TRANSACTIONTYPE;
        }

        $xactions[] = id(new PhabricatorEditEngineConfigurationTransaction())
          ->setTransactionType($xaction_type)
          ->setNewValue($order);

        $editor = id(new PhabricatorEditEngineConfigurationEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true);

        $editor->applyTransactions($config, $xactions);

        $order++;
      }

      return id(new AphrontRedirectResponse())
        ->setURI($cancel_uri);
    }

    $list_id = celerity_generate_unique_node_id();
    $input_id = celerity_generate_unique_node_id();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setID($list_id)
      ->setFlush(true);

    $form_order = array();
    foreach ($configs as $config) {
      $name = $config->getName();
      $identifier = $config->getIdentifier();

      $item = id(new PHUIObjectItemView())
        ->setHeader($name)
        ->setGrippable(true)
        ->addSigil('editengine-form-config')
        ->setMetadata(
          array(
            'formIdentifier' => $identifier,
          ));

      $list->addItem($item);

      $form_order[] = $identifier;
    }

    Javelin::initBehavior(
      'editengine-reorder-configs',
      array(
        'listID' => $list_id,
        'inputID' => $input_id,
        'reorderURI' => $reorder_uri,
      ));

    if ($is_create) {
      $title = pht('Reorder Create Forms');
      $button = pht('Save Create Order');

      $note_text = pht(
        'Drag and drop fields to change the order in which they appear in '.
        'the application "Create" menu.');
    } else {
      $title = pht('Reorder Edit Forms');
      $button = pht('Save Edit Order');

      $note_text = pht(
        'Drag and drop fields to change their priority for edits. When a '.
        'user edits an object, they will be shown the first form in this '.
        'list that they have permission to see.');
    }

    $note = id(new PHUIInfoView())
      ->appendChild($note_text)
      ->setSeverity(PHUIInfoView::SEVERITY_NOTICE);

    $input = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'formOrder',
        'value' => implode(', ', $form_order),
        'id' => $input_id,
      ));

    return $this->newDialog()
      ->setTitle($title)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($note)
      ->appendChild($list)
      ->appendChild($input)
      ->addSubmitButton(pht('Save Changes'))
      ->addCancelButton($cancel_uri);
  }

}

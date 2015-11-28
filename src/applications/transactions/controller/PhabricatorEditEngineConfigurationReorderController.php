<?php

final class PhabricatorEditEngineConfigurationReorderController
  extends PhabricatorEditEngineController {

  public function handleRequest(AphrontRequest $request) {
    $engine_key = $request->getURIData('engineKey');
    $this->setEngineKey($engine_key);

    $key = $request->getURIData('key');
    $viewer = $this->getViewer();

    $config = id(new PhabricatorEditEngineConfigurationQuery())
      ->setViewer($viewer)
      ->withEngineKeys(array($engine_key))
      ->withIdentifiers(array($key))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$config) {
      return id(new Aphront404Response());
    }

    $cancel_uri = "/transactions/editengine/{$engine_key}/view/{$key}/";
    $reorder_uri = "/transactions/editengine/{$engine_key}/reorder/{$key}/";

    if ($request->isFormPost()) {
      $xactions = array();
      $key_order = $request->getStrList('keyOrder');

      $type_order = PhabricatorEditEngineConfigurationTransaction::TYPE_ORDER;

      $xactions[] = id(new PhabricatorEditEngineConfigurationTransaction())
        ->setTransactionType($type_order)
        ->setNewValue($key_order);

      $editor = id(new PhabricatorEditEngineConfigurationEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true);

      $editor->applyTransactions($config, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($cancel_uri);
    }

    $engine = $config->getEngine();
    $fields = $engine->getFieldsForConfig($config);

    $list_id = celerity_generate_unique_node_id();
    $input_id = celerity_generate_unique_node_id();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setID($list_id)
      ->setFlush(true);

    $key_order = array();
    foreach ($fields as $field) {
      if (!$field->getIsReorderable()) {
        continue;
      }

      $label = $field->getLabel();
      $key = $field->getKey();

      if ($label !== null) {
        $header = $label;
      } else {
        $header = $key;
      }

      $item = id(new PHUIObjectItemView())
        ->setHeader($header)
        ->setGrippable(true)
        ->addSigil('editengine-form-field')
        ->setMetadata(
          array(
            'fieldKey' => $key,
          ));

      $list->addItem($item);

      $key_order[] = $key;
    }

    Javelin::initBehavior(
      'editengine-reorder-fields',
      array(
        'listID' => $list_id,
        'inputID' => $input_id,
        'reorderURI' => $reorder_uri,
      ));

    $note = id(new PHUIInfoView())
      ->appendChild(pht('Drag and drop fields to reorder them.'))
      ->setSeverity(PHUIInfoView::SEVERITY_NOTICE);

    $input = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'keyOrder',
        'value' => implode(', ', $key_order),
        'id' => $input_id,
      ));

    return $this->newDialog()
      ->setTitle(pht('Reorder Fields'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($note)
      ->appendChild($list)
      ->appendChild($input)
      ->addSubmitButton(pht('Save Changes'))
      ->addCancelButton($cancel_uri);
  }

}

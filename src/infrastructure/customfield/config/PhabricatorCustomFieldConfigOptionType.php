<?php

final class PhabricatorCustomFieldConfigOptionType
  extends PhabricatorConfigOptionType {

  public function readRequest(
    PhabricatorConfigOption $option,
    AphrontRequest $request) {

    $e_value = null;
    $errors = array();
    $storage_value = $request->getStr('value');

    $in_value = phutil_json_decode($storage_value);

    // When we submit from JS, we submit a list (since maps are not guaranteed
    // to retain order). Convert it into a map for storage (since it's far more
    // convenient for us elsewhere).
    $storage_value = ipull($in_value, null, 'key');
    $display_value = $storage_value;

    return array($e_value, $errors, $storage_value, $display_value);
  }

  public function renderControl(
    PhabricatorConfigOption $option,
    $display_value,
    $e_value) {

    $field_base_class = $option->getCustomData();

    $field_spec = $display_value;
    if (!is_array($field_spec)) {
      $field_spec = PhabricatorEnv::getEnvConfig($option->getKey());
    }

    // TODO: We might need to build a real object here eventually.
    $faux_object = null;

    $fields = PhabricatorCustomField::buildFieldList(
      $field_base_class,
      $field_spec,
      $faux_object,
      array(
        'withDisabled' => true,
      ));

    $list_id = celerity_generate_unique_node_id();
    $input_id = celerity_generate_unique_node_id();

    $list = id(new PHUIObjectItemListView())
      ->setFlush(true)
      ->setID($list_id);
    foreach ($fields as $key => $field) {
      $item = id(new PHUIObjectItemView())
        ->addSigil('field-spec')
        ->setMetadata(array('fieldKey' => $key))
        ->setGrippable(true)
        ->addAttribute($field->getFieldDescription())
        ->setHeader($field->getFieldName());

      $spec = idx($field_spec, $key, array());
      $is_disabled = idx($spec, 'disabled', $field->shouldDisableByDefault());

      $disabled_item = clone $item;
      $enabled_item = clone $item;

      if ($is_disabled) {
        $list->addItem($disabled_item);
      } else {
        $list->addItem($enabled_item);
      }

      $disabled_item->addIcon('none', pht('Disabled'));
      $disabled_item->setDisabled(true);
      $disabled_item->addAction(
        id(new PHUIListItemView())
          ->setHref('#')
          ->addSigil('field-spec-toggle')
          ->setIcon('fa-plus'));

      $enabled_item->setStatusIcon('fa-check green');

      if (!$field->canDisableField()) {
        $enabled_item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-lock grey'));
        $enabled_item->addIcon('none', pht('Permanent Field'));
      } else {
        $enabled_item->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->addSigil('field-spec-toggle')
            ->setIcon('fa-times'));
      }

      $fields[$key] = array(
        'disabled' => $is_disabled,
        'disabledMarkup' => $disabled_item->render(),
        'enabledMarkup' => $enabled_item->render(),
      );
    }

    $input = phutil_tag(
      'input',
      array(
        'id' => $input_id,
        'type' => 'hidden',
        'name' => 'value',
        'value' => '',
      ));

    Javelin::initBehavior(
      'config-reorder-fields',
      array(
        'listID' => $list_id,
        'inputID' => $input_id,
        'fields' => $fields,
      ));

    return id(new AphrontFormMarkupControl())
      ->setLabel(pht('Value'))
      ->setError($e_value)
      ->setValue(
        array(
          $list,
          $input,
        ));
  }

}

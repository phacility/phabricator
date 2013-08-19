<?php

/**
 * Convenience class to perform operations on an entire field list, like reading
 * all values from storage.
 *
 *   $field_list = new PhabricatorCustomFieldList($fields);
 *
 */
final class PhabricatorCustomFieldList extends Phobject {

  private $fields;

  public function __construct(array $fields) {
    assert_instances_of($fields, 'PhabricatorCustomField');
    $this->fields = $fields;
  }

  public function getFields() {
    return $this->fields;
  }

  /**
   * Read stored values for all fields which support storage.
   *
   * @param PhabricatorCustomFieldInterface Object to read field values for.
   * @return void
   */
  public function readFieldsFromStorage(
    PhabricatorCustomFieldInterface $object) {

    $keys = array();
    foreach ($this->fields as $field) {
      if ($field->shouldEnableForRole(PhabricatorCustomField::ROLE_STORAGE)) {
        $keys[$field->getFieldIndex()] = $field;
      }
    }

    if (!$keys) {
      return;
    }

    // NOTE: We assume all fields share the same storage. This isn't guaranteed
    // to be true, but always is for now.

    $table = head($keys)->newStorageObject();

    $objects = $table->loadAllWhere(
      'objectPHID = %s AND fieldIndex IN (%Ls)',
      $object->getPHID(),
      array_keys($keys));
    $objects = mpull($objects, null, 'getFieldIndex');

    foreach ($keys as $key => $field) {
      $storage = idx($objects, $key);
      if ($storage) {
        $field->setValueFromStorage($storage->getFieldValue());
      } else {
        $field->setValueFromStorage(null);
      }
    }
  }

  public function appendFieldsToForm(AphrontFormView $form) {
    foreach ($this->fields as $field) {
      if ($field->shouldEnableForRole(PhabricatorCustomField::ROLE_EDIT)) {
        $form->appendChild($field->renderEditControl());
      }
    }
  }

  public function appendFieldsToPropertyList(
    PhabricatorCustomFieldInterface $object,
    PhabricatorUser $viewer,
    PhabricatorPropertyListView $view) {

    $this->readFieldsFromStorage($object);
    $fields = $this->fields;

    foreach ($fields as $field) {
      $field->setViewer($viewer);
    }

    // Move all the blocks to the end, regardless of their configuration order,
    // because it always looks silly to render a block in the middle of a list
    // of properties.
    $head = array();
    $tail = array();
    foreach ($fields as $key => $field) {
      $style = $field->getStyleForPropertyView();
      switch ($style) {
        case 'property':
          $head[$key] = $field;
          break;
        case 'block':
          $tail[$key] = $field;
          break;
        default:
          throw new Exception(
            "Unknown field property view style '{$style}'; valid styles are ".
            "'block' and 'property'.");
      }
    }
    $fields = $head + $tail;

    foreach ($fields as $field) {
      $label = $field->renderPropertyViewLabel();
      $value = $field->renderPropertyViewValue();
      if ($value !== null) {
        switch ($field->getStyleForPropertyView()) {
          case 'property':
            $view->addProperty($label, $value);
            break;
          case 'block':
            $view->invokeWillRenderEvent();
            if ($label !== null) {
              $view->addSectionHeader($label);
            }
            $view->addTextContent($value);
            break;
        }
      }
    }
  }

  public function buildFieldTransactionsFromRequest(
    PhabricatorApplicationTransaction $template,
    AphrontRequest $request) {

    $xactions = array();

    $role = PhabricatorCustomField::ROLE_APPLICATIONTRANSACTIONS;
    foreach ($this->fields as $field) {
      if (!$field->shouldEnableForRole($role)) {
        continue;
      }

      $old_value = $field->getOldValueForApplicationTransactions();

      $field->readValueFromRequest($request);

      $xaction = id(clone $template)
        ->setTransactionType(PhabricatorTransactions::TYPE_CUSTOMFIELD)
        ->setMetadataValue('customfield:key', $field->getFieldKey())
        ->setOldValue($old_value)
        ->setNewValue($field->getNewValueForApplicationTransactions());

      $xactions[] = $xaction;
    }

    return $xactions;
  }

}

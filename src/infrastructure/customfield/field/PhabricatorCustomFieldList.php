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

  public function setViewer(PhabricatorUser $viewer) {
    foreach ($this->getFields() as $field) {
      $field->setViewer($viewer);
    }
    return $this;
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

    $objects = array();
    if ($object->getPHID()) {
      $objects = $table->loadAllWhere(
        'objectPHID = %s AND fieldIndex IN (%Ls)',
        $object->getPHID(),
        array_keys($keys));
      $objects = mpull($objects, null, 'getFieldIndex');
    }

    foreach ($keys as $key => $field) {
      $storage = idx($objects, $key);
      if ($storage) {
        $field->setValueFromStorage($storage->getFieldValue());
      } else if ($object->getPHID()) {
        // NOTE: We set this only if the object exists. Otherwise, we allow the
        // field to retain any default value it may have.
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
        case 'header':
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

    $add_header = null;

    foreach ($fields as $field) {
      $label = $field->renderPropertyViewLabel();
      $value = $field->renderPropertyViewValue();
      if ($value !== null) {
        switch ($field->getStyleForPropertyView()) {
          case 'header':
            // We want to hide headers if the fields the're assciated with
            // don't actually produce any visible properties. For example, in a
            // list like this:
            //
            //   Header A
            //   Prop A: Value A
            //   Header B
            //   Prop B: Value B
            //
            // ...if the "Prop A" field returns `null` when rendering its
            // property value and we rendered naively, we'd get this:
            //
            //   Header A
            //   Header B
            //   Prop B: Value B
            //
            // This is silly. Instead, we hide "Header A".
            $add_header = $value;
            break;
          case 'property':
            if ($add_header !== null) {
              // Add the most recently seen header.
              $view->addSectionHeader($add_header);
              $add_header = null;
            }
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


  /**
   * Publish field indexes into index tables, so ApplicationSearch can search
   * them.
   *
   * @return void
   */
  public function rebuildIndexes(PhabricatorCustomFieldInterface $object) {
    $indexes = array();
    $index_keys = array();

    $phid = $object->getPHID();

    $role = PhabricatorCustomField::ROLE_APPLICATIONSEARCH;
    foreach ($this->fields as $field) {
      if (!$field->shouldEnableForRole($role)) {
        continue;
      }

      $index_keys[$field->getFieldIndex()] = true;

      foreach ($field->buildFieldIndexes() as $index) {
        $index->setObjectPHID($phid);
        $indexes[$index->getTableName()][] = $index;
      }
    }

    if (!$indexes) {
      return;
    }

    $any_index = head(head($indexes));
    $conn_w = $any_index->establishConnection('w');

    foreach ($indexes as $table => $index_list) {
      $sql = array();
      foreach ($index_list as $index) {
        $sql[] = $index->formatForInsert($conn_w);
      }
      $indexes[$table] = $sql;
    }

    $any_index->openTransaction();

      foreach ($indexes as $table => $sql_list) {
        queryfx(
          $conn_w,
          'DELETE FROM %T WHERE objectPHID = %s AND indexKey IN (%Ls)',
          $table,
          $phid,
          array_keys($index_keys));

        if (!$sql_list) {
          continue;
        }

        foreach (PhabricatorLiskDAO::chunkSQL($sql_list) as $chunk) {
          queryfx(
            $conn_w,
            'INSERT INTO %T (objectPHID, indexKey, indexValue) VALUES %Q',
            $table,
            $chunk);
        }
      }

    $any_index->saveTransaction();
  }

}

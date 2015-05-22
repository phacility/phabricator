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
  private $viewer;

  public function __construct(array $fields) {
    assert_instances_of($fields, 'PhabricatorCustomField');
    $this->fields = $fields;
  }

  public function getFields() {
    return $this->fields;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
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

    foreach ($this->fields as $field) {
      $field->setObject($object);
      $field->readValueFromObject($object);
    }

    $keys = array();
    foreach ($this->fields as $field) {
      if ($field->shouldEnableForRole(PhabricatorCustomField::ROLE_STORAGE)) {
        $keys[$field->getFieldIndex()] = $field;
      }
    }

    if (!$keys) {
      return $this;
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

    return $this;
  }

  public function appendFieldsToForm(AphrontFormView $form) {
    $enabled = array();
    foreach ($this->fields as $field) {
      if ($field->shouldEnableForRole(PhabricatorCustomField::ROLE_EDIT)) {
        $enabled[] = $field;
      }
    }

    $phids = array();
    foreach ($enabled as $field_key => $field) {
      $phids[$field_key] = $field->getRequiredHandlePHIDsForEdit();
    }

    $all_phids = array_mergev($phids);
    if ($all_phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->viewer)
        ->withPHIDs($all_phids)
        ->execute();
    } else {
      $handles = array();
    }

    foreach ($enabled as $field_key => $field) {
      $field_handles = array_select_keys($handles, $phids[$field_key]);

      $instructions = $field->getInstructionsForEdit();
      if (strlen($instructions)) {
        $form->appendRemarkupInstructions($instructions);
      }

      $form->appendChild($field->renderEditControl($field_handles));
    }
  }

  public function appendFieldsToPropertyList(
    PhabricatorCustomFieldInterface $object,
    PhabricatorUser $viewer,
    PHUIPropertyListView $view) {

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
            pht(
              "Unknown field property view style '%s'; valid styles are ".
              "'%s' and '%s'.",
              $style,
              'block',
              'property'));
      }
    }
    $fields = $head + $tail;

    $add_header = null;

    $phids = array();
    foreach ($fields as $key => $field) {
      $phids[$key] = $field->getRequiredHandlePHIDsForPropertyView();
    }

    $all_phids = array_mergev($phids);
    if ($all_phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs($all_phids)
        ->execute();
    } else {
      $handles = array();
    }

    foreach ($fields as $key => $field) {
      $field_handles = array_select_keys($handles, $phids[$key]);
      $label = $field->renderPropertyViewLabel();
      $value = $field->renderPropertyViewValue($field_handles);
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
            $icon = $field->getIconForPropertyView();
            $view->invokeWillRenderEvent();
            if ($label !== null) {
              $view->addSectionHeader($label, $icon);
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

      $transaction_type = $field->getApplicationTransactionType();
      $xaction = id(clone $template)
        ->setTransactionType($transaction_type);

      if ($transaction_type == PhabricatorTransactions::TYPE_CUSTOMFIELD) {
        // For TYPE_CUSTOMFIELD transactions only, we provide the old value
        // as an input.
        $old_value = $field->getOldValueForApplicationTransactions();
        $xaction->setOldValue($old_value);
      }

      $field->readValueFromRequest($request);

      $xaction
        ->setNewValue($field->getNewValueForApplicationTransactions());

      if ($transaction_type == PhabricatorTransactions::TYPE_CUSTOMFIELD) {
        // For TYPE_CUSTOMFIELD transactions, add the field key in metadata.
        $xaction->setMetadataValue('customfield:key', $field->getFieldKey());
      }

      $metadata = $field->getApplicationTransactionMetadata();
      foreach ($metadata as $key => $value) {
        $xaction->setMetadataValue($key, $value);
      }

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

  public function updateAbstractDocument(
    PhabricatorSearchAbstractDocument $document) {

    $role = PhabricatorCustomField::ROLE_GLOBALSEARCH;
    foreach ($this->getFields() as $field) {
      if (!$field->shouldEnableForRole($role)) {
        continue;
      }
      $field->updateAbstractDocument($document);
    }
  }


}

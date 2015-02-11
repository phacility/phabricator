<?php

final class AlmanacPropertyEditController
  extends AlmanacDeviceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($request->getStr('objectPHID')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    if (!($object instanceof AlmanacPropertyInterface)) {
      return new Aphront404Response();
    }

    $cancel_uri = $object->getURI();

    $key = $request->getStr('key');
    if ($key) {
      $property_key = $key;

      $is_new = false;
      $title = pht('Edit Property');
      $save_button = pht('Save Changes');
    } else {
      $property_key = null;

      $is_new = true;
      $title = pht('Add Property');
      $save_button = pht('Add Property');
    }

    if ($is_new) {
      $errors = array();
      $property = null;

      $v_name = null;
      $e_name = true;

      if ($request->isFormPost()) {
        $name = $request->getStr('name');
        if (!strlen($name)) {
          $e_name = pht('Required');
          $errors[] = pht('You must provide a property name.');
        } else {
          $caught = null;
          try {
            AlmanacNames::validateServiceOrDeviceName($name);
          } catch (Exception $ex) {
            $caught = $ex;
          }
          if ($caught) {
            $e_name = pht('Invalid');
            $errors[] = $caught->getMessage();
          }
        }

        if (!$errors) {
          $property_key = $name;
        }
      }

      if ($property_key === null) {
        $form = id(new AphrontFormView())
          ->setUser($viewer)
          ->appendChild(
            id(new AphrontFormTextControl())
              ->setName('name')
              ->setLabel(pht('Name'))
              ->setValue($v_name)
              ->setError($e_name));

        return $this->newDialog()
          ->setTitle($title)
          ->setErrors($errors)
          ->addHiddenInput('objectPHID', $request->getStr('objectPHID'))
          ->appendForm($form)
          ->addSubmitButton(pht('Continue'))
          ->addCancelButton($cancel_uri);
      }
    }

    // Make sure property key is appropriate.
    // TODO: It would be cleaner to put this safety check in the Editor.
    AlmanacNames::validateServiceOrDeviceName($property_key);

    // If we're adding a new property, put a placeholder on the object so
    // that we can build a CustomField for it.
    if (!$object->hasAlmanacProperty($property_key)) {
      $temporary_property = id(new AlmanacProperty())
        ->setObjectPHID($object->getPHID())
        ->setFieldName($property_key);

      $object->attachAlmanacProperties(array($temporary_property));
    }

    $field_list = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_DEFAULT);

    // Select only the field being edited.
    $fields = $field_list->getFields();
    $fields = array_select_keys($fields, array($property_key));
    $field_list = new PhabricatorCustomFieldList($fields);

    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($object);

    $validation_exception = null;
    if ($request->isFormPost() && $request->getStr('isValueEdit')) {
      $xactions = $field_list->buildFieldTransactionsFromRequest(
        $object->getApplicationTransactionTemplate(),
        $request);

      $editor = $object->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      try {
        $editor->applyTransactions($object, $xactions);
        return id(new AphrontRedirectResponse())->setURI($cancel_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('objectPHID', $request->getStr('objectPHID'))
      ->addHiddenInput('key', $request->getStr('key'))
      ->addHiddenInput('name', $property_key)
      ->addHiddenInput('isValueEdit', true);

    $field_list->appendFieldsToForm($form);

    return $this->newDialog()
      ->setTitle($title)
      ->setValidationException($validation_exception)
      ->appendForm($form)
      ->addSubmitButton($save_button)
      ->addCancelButton($cancel_uri);
  }

}

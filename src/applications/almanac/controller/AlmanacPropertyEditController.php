<?php

final class AlmanacPropertyEditController
  extends AlmanacDeviceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $id = $request->getURIData('id');
    if ($id) {
      $property = id(new AlmanacPropertyQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$property) {
        return new Aphront404Response();
      }

      $object = $property->getObject();

      $is_new = false;
      $title = pht('Edit Property');
      $save_button = pht('Save Changes');
    } else {
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

      $is_new = true;
      $title = pht('Add Property');
      $save_button = pht('Add Property');
    }

    if (!($object instanceof AlmanacPropertyInterface)) {
      return new Aphront404Response();
    }

    $cancel_uri = $object->getURI();

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
          $property = id(new AlmanacPropertyQuery())
            ->setViewer($viewer)
            ->withObjectPHIDs(array($object->getPHID()))
            ->withNames(array($name))
            ->requireCapabilities(
              array(
                PhabricatorPolicyCapability::CAN_VIEW,
                PhabricatorPolicyCapability::CAN_EDIT,
              ))
            ->executeOne();
          if (!$property) {
            $property = id(new AlmanacProperty())
              ->setObjectPHID($object->getPHID())
              ->setFieldName($name);
          }
        }
      }

      if (!$property) {
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

    $v_name = $property->getFieldName();
    $e_name = true;

    $v_value = $property->getFieldValue();
    $e_value = null;

    $object->attachAlmanacProperties(array($property));

    $field_list = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_EDIT);
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
      ->addHiddenInput('name', $request->getStr('name'))
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

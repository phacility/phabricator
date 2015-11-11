<?php

final class PhluxEditController extends PhluxController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $key = $request->getURIData('key');

    $is_new = ($key === null);
    if ($is_new) {
      $var = new PhluxVariable();
      $var->setViewPolicy(PhabricatorPolicies::POLICY_USER);
      $var->setEditPolicy(PhabricatorPolicies::POLICY_USER);
    } else {
      $var = id(new PhluxVariableQuery())
        ->setViewer($viewer)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->withKeys(array($key))
        ->executeOne();
      if (!$var) {
        return new Aphront404Response();
      }
      $view_uri = $this->getApplicationURI('/view/'.$key.'/');
    }

    $e_key = ($is_new ? true : null);
    $e_value = true;
    $errors = array();

    $key = $var->getVariableKey();

    $display_value = null;
    $value = $var->getVariableValue();

    if ($request->isFormPost()) {
      if ($is_new) {
        $key = $request->getStr('key');
        if (!strlen($key)) {
          $errors[] = pht('Variable key is required.');
          $e_key = pht('Required');
        } else if (!preg_match('/^[a-z0-9.-]+\z/', $key)) {
          $errors[] = pht(
            'Variable key "%s" must contain only lowercase letters, digits, '.
            'period, and hyphen.',
            $key);
          $e_key = pht('Invalid');
        }
      }

      $raw_value = $request->getStr('value');
      $value = json_decode($raw_value, true);
      if ($value === null && strtolower($raw_value) !== 'null') {
        $e_value = pht('Invalid');
        $errors[] = pht('Variable value must be valid JSON.');
        $display_value = $raw_value;
      }

      if (!$errors) {
        $editor = id(new PhluxVariableEditor())
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request);

        $xactions = array();
        $xactions[] = id(new PhluxTransaction())
          ->setTransactionType(PhluxTransaction::TYPE_EDIT_KEY)
          ->setNewValue($key);

        $xactions[] = id(new PhluxTransaction())
          ->setTransactionType(PhluxTransaction::TYPE_EDIT_VALUE)
          ->setNewValue($value);

        $xactions[] = id(new PhluxTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
          ->setNewValue($request->getStr('viewPolicy'));

        $xactions[] = id(new PhluxTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
          ->setNewValue($request->getStr('editPolicy'));

        try {
          $editor->applyTransactions($var, $xactions);
          $view_uri = $this->getApplicationURI('/view/'.$key.'/');
          return id(new AphrontRedirectResponse())->setURI($view_uri);
        } catch (AphrontDuplicateKeyQueryException $ex) {
          $e_key = pht('Not Unique');
          $errors[] = pht('Variable key must be unique.');
        }
      }
    }

    if ($display_value === null) {
      if (is_array($value) &&
          (array_keys($value) !== array_keys(array_values($value)))) {
        $json = new PhutilJSON();
        $display_value = $json->encodeFormatted($value);
      } else {
        $display_value = json_encode($value);
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($var)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setValue($var->getVariableKey())
          ->setLabel(pht('Key'))
          ->setName('key')
          ->setError($e_key)
          ->setCaption(pht('Lowercase letters, digits, dot and hyphen only.'))
          ->setDisabled(!$is_new))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setValue($display_value)
          ->setLabel(pht('Value'))
          ->setName('value')
          ->setCaption(pht('Enter value as JSON.'))
          ->setError($e_value))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($var)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($var)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicies($policies));

    if ($is_new) {
      $form->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Create Variable')));
    } else {
      $form->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Update Variable'))
          ->addCancelButton($view_uri));
    }

    $crumbs = $this->buildApplicationCrumbs();

    if ($is_new) {
      $title = pht('Create Variable');
      $crumbs->addTextCrumb($title, $request->getRequestURI());
    } else {
      $title = pht('Edit %s', $key);
      $crumbs->addTextCrumb($title, $request->getRequestURI());
    }

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => $title,
      ));
  }

}

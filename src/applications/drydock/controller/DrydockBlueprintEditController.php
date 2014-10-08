<?php

final class DrydockBlueprintEditController extends DrydockBlueprintController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if ($this->id) {
      $blueprint = id(new DrydockBlueprintQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$blueprint) {
        return new Aphront404Response();
      }

      $impl = $blueprint->getImplementation();
      $cancel_uri = $this->getApplicationURI('blueprint/'.$this->id.'/');
    } else {
      $this->requireApplicationCapability(
        DrydockCreateBlueprintsCapability::CAPABILITY);

      $class = $request->getStr('class');

      $impl = DrydockBlueprintImplementation::getNamedImplementation($class);
      if (!$impl || !$impl->isEnabled()) {
        return new Aphront400Response();
      }

      $blueprint = DrydockBlueprint::initializeNewBlueprint($viewer);
      $blueprint->setClassName($class);
      $cancel_uri = $this->getApplicationURI('blueprint/');
    }

    $field_list = PhabricatorCustomField::getObjectFields(
      $blueprint,
      PhabricatorCustomField::ROLE_EDIT);
    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($blueprint);

    $v_name = $blueprint->getBlueprintName();
    $e_name = true;
    $errors = array();
    $validation_exception = null;

    if ($request->isFormPost()) {
      $v_view_policy = $request->getStr('viewPolicy');
      $v_edit_policy = $request->getStr('editPolicy');
      $v_name = $request->getStr('name');
      if (!strlen($v_name)) {
        $e_name = pht('Required');
        $errors[] = pht('You must name this blueprint.');
      }

      if (!$errors) {
        $xactions = array();

        $xactions = $field_list->buildFieldTransactionsFromRequest(
          new DrydockBlueprintTransaction(),
          $request);

        $xactions[] = id(new DrydockBlueprintTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
          ->setNewValue($v_view_policy);

        $xactions[] = id(new DrydockBlueprintTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
          ->setNewValue($v_edit_policy);

        $xactions[] = id(new DrydockBlueprintTransaction())
          ->setTransactionType(DrydockBlueprintTransaction::TYPE_NAME)
          ->setNewValue($v_name);

        $editor = id(new DrydockBlueprintEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true);

        try {
          $editor->applyTransactions($blueprint, $xactions);

          $id = $blueprint->getID();
          $save_uri = $this->getApplicationURI("blueprint/{$id}/");

          return id(new AphrontRedirectResponse())->setURI($save_uri);
        } catch (PhabricatorApplicationTransactionValidationException $ex) {
          $validation_exception = $ex;
        }
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($blueprint)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('class', $request->getStr('class'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Blueprint Type'))
          ->setValue($impl->getBlueprintName()))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($blueprint)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($blueprint)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicies($policies));

    $field_list->appendFieldsToForm($form);

    $crumbs = $this->buildApplicationCrumbs();

    if ($blueprint->getID()) {
      $title = pht('Edit Blueprint');
      $header = pht('Edit Blueprint %d', $blueprint->getID());
      $crumbs->addTextCrumb(pht('Blueprint %d', $blueprint->getID()));
      $crumbs->addTextCrumb(pht('Edit'));
      $submit = pht('Save Blueprint');
    } else {
      $title = pht('New Blueprint');
      $header = pht('New Blueprint');
      $crumbs->addTextCrumb(pht('New Blueprint'));
      $submit = pht('Create Blueprint');
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue($submit)
        ->addCancelButton($cancel_uri));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($header)
      ->setValidationException($validation_exception)
      ->setFormErrors($errors)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }

}

<?php

final class PhabricatorProjectTriggerEditController
  extends PhabricatorProjectTriggerController {

  public function handleRequest(AphrontRequest $request) {
    $request = $this->getRequest();
    $viewer = $request->getViewer();

    $id = $request->getURIData('id');
    if ($id) {
      $trigger = id(new PhabricatorProjectTriggerQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$trigger) {
        return new Aphront404Response();
      }
    } else {
      $trigger = PhabricatorProjectTrigger::initializeNewTrigger();
    }

    $trigger->setViewer($viewer);

    $column_phid = $request->getStr('columnPHID');
    if ($column_phid) {
      $column = id(new PhabricatorProjectColumnQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($column_phid))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$column) {
        return new Aphront404Response();
      }
      $board_uri = $column->getWorkboardURI();
    } else {
      $column = null;
      $board_uri = null;
    }

    if ($board_uri) {
      $cancel_uri = $board_uri;
    } else if ($trigger->getID()) {
      $cancel_uri = $trigger->getURI();
    } else {
      $cancel_uri = $this->getApplicationURI('trigger/');
    }

    $v_name = $trigger->getName();
    $v_edit = $trigger->getEditPolicy();
    $v_rules = $trigger->getTriggerRules();

    $e_name = null;
    $e_edit = null;

    $validation_exception = null;
    if ($request->isFormPost()) {
      try {
        $v_name = $request->getStr('name');
        $v_edit = $request->getStr('editPolicy');

        // Read the JSON rules from the request and convert them back into
        // "TriggerRule" objects so we can render the correct form state
        // if the user is modifying the rules
        $raw_rules = $request->getStr('rules');
        $raw_rules = phutil_json_decode($raw_rules);

        $copy = clone $trigger;
        $copy->setRuleset($raw_rules);
        $v_rules = $copy->getTriggerRules();

        $xactions = array();
        if (!$trigger->getID()) {
          $xactions[] = $trigger->getApplicationTransactionTemplate()
            ->setTransactionType(PhabricatorTransactions::TYPE_CREATE)
            ->setNewValue(true);
        }

        $xactions[] = $trigger->getApplicationTransactionTemplate()
          ->setTransactionType(
            PhabricatorProjectTriggerNameTransaction::TRANSACTIONTYPE)
          ->setNewValue($v_name);

        $xactions[] = $trigger->getApplicationTransactionTemplate()
          ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
          ->setNewValue($v_edit);

        $xactions[] = $trigger->getApplicationTransactionTemplate()
          ->setTransactionType(
            PhabricatorProjectTriggerRulesetTransaction::TRANSACTIONTYPE)
          ->setNewValue($raw_rules);

        $editor = $trigger->getApplicationTransactionEditor()
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true);

        $editor->applyTransactions($trigger, $xactions);

        $next_uri = $trigger->getURI();

        if ($column) {
          $column_xactions = array();

          $column_xactions[] = $column->getApplicationTransactionTemplate()
            ->setTransactionType(
              PhabricatorProjectColumnTriggerTransaction::TRANSACTIONTYPE)
            ->setNewValue($trigger->getPHID());

          $column_editor = $column->getApplicationTransactionEditor()
            ->setActor($viewer)
            ->setContentSourceFromRequest($request)
            ->setContinueOnNoEffect(true)
            ->setContinueOnMissingFields(true);

          $column_editor->applyTransactions($column, $column_xactions);

          $next_uri = $column->getWorkboardURI();
        }

        return id(new AphrontRedirectResponse())->setURI($next_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_name = $ex->getShortMessage(
          PhabricatorProjectTriggerNameTransaction::TRANSACTIONTYPE);

        $e_edit = $ex->getShortMessage(
          PhabricatorTransactions::TYPE_EDIT_POLICY);

        $trigger->setEditPolicy($v_edit);
      }
    }

    if ($trigger->getID()) {
      $title = $trigger->getObjectName();
      $submit = pht('Save Trigger');
      $header = pht('Edit Trigger: %s', $trigger->getObjectName());
    } else {
      $title = pht('New Trigger');
      $submit = pht('Create Trigger');
      $header = pht('New Trigger');
    }

    $form_id = celerity_generate_unique_node_id();
    $table_id = celerity_generate_unique_node_id();
    $create_id = celerity_generate_unique_node_id();
    $input_id = celerity_generate_unique_node_id();

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->setID($form_id);

    if ($column) {
      $form->addHiddenInput('columnPHID', $column->getPHID());
    }

    $form->appendControl(
      id(new AphrontFormTextControl())
        ->setLabel(pht('Name'))
        ->setName('name')
        ->setValue($v_name)
        ->setError($e_name)
        ->setPlaceholder($trigger->getDefaultName()));

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($trigger)
      ->execute();

    $form->appendControl(
      id(new AphrontFormPolicyControl())
        ->setName('editPolicy')
        ->setPolicyObject($trigger)
        ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
        ->setPolicies($policies)
        ->setError($e_edit));

    $form->appendChild(
      phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => 'rules',
          'id' => $input_id,
        )));

    $form->appendChild(
      id(new PHUIFormInsetView())
        ->setTitle(pht('Rules'))
        ->setDescription(
          pht(
            'When a card is dropped into a column which uses this trigger:'))
        ->setRightButton(
          javelin_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button button-green',
              'id' => $create_id,
              'mustcapture' => true,
            ),
            pht('New Rule')))
        ->setContent(
          javelin_tag(
            'table',
            array(
              'id' => $table_id,
              'class' => 'trigger-rules-table',
            ))));

    $this->setupEditorBehavior(
      $trigger,
      $v_rules,
      $form_id,
      $table_id,
      $create_id,
      $input_id);

    $form->appendControl(
      id(new AphrontFormSubmitControl())
        ->setValue($submit)
        ->addCancelButton($cancel_uri));

    $header = id(new PHUIHeaderView())
      ->setHeader($header);

    $box_view = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setValidationException($validation_exception)
      ->appendChild($form);

    $column_view = id(new PHUITwoColumnView())
      ->setFooter($box_view);

    $crumbs = $this->buildApplicationCrumbs()
      ->setBorder(true);

    if ($column) {
      $crumbs->addTextCrumb(
        pht(
          '%s: %s',
          $column->getProject()->getDisplayName(),
          $column->getName()),
        $board_uri);
    }

    $crumbs->addTextCrumb($title);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($column_view);
  }

  private function setupEditorBehavior(
    PhabricatorProjectTrigger $trigger,
    array $rule_list,
    $form_id,
    $table_id,
    $create_id,
    $input_id) {

    $rule_list = mpull($rule_list, 'toDictionary');
    $rule_list = array_values($rule_list);

    $type_list = PhabricatorProjectTriggerRule::getAllTriggerRules();

    foreach ($type_list as $rule) {
      $rule->setViewer($this->getViewer());
    }
    $type_list = mpull($type_list, 'newTemplate');
    $type_list = array_values($type_list);

    require_celerity_resource('project-triggers-css');

    Javelin::initBehavior(
      'trigger-rule-editor',
      array(
        'formNodeID' => $form_id,
        'tableNodeID' => $table_id,
        'createNodeID' => $create_id,
        'inputNodeID' => $input_id,

        'rules' => $rule_list,
        'types' => $type_list,
      ));
  }

}

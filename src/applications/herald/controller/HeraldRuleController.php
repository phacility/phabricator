<?php

final class HeraldRuleController extends HeraldController {

  private $id;
  private $filter;

  public function willProcessRequest(array $data) {
    $this->id = (int)idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $content_type_map = HeraldAdapter::getEnabledAdapterMap($user);
    $rule_type_map = HeraldRuleTypeConfig::getRuleTypeMap();

    if ($this->id) {
      $id = $this->id;
      $rule = id(new HeraldRuleQuery())
        ->setViewer($user)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$rule) {
        return new Aphront404Response();
      }
      $cancel_uri = $this->getApplicationURI("rule/{$id}/");
    } else {
      $rule = new HeraldRule();
      $rule->setAuthorPHID($user->getPHID());
      $rule->setMustMatchAll(1);

      $content_type = $request->getStr('content_type');
      $rule->setContentType($content_type);

      $rule_type = $request->getStr('rule_type');
      if (!isset($rule_type_map[$rule_type])) {
        $rule_type = HeraldRuleTypeConfig::RULE_TYPE_PERSONAL;
      }
      $rule->setRuleType($rule_type);

      $adapter = HeraldAdapter::getAdapterForContentType(
        $rule->getContentType());

      if (!$adapter->supportsRuleType($rule->getRuleType())) {
        throw new Exception(
          pht(
            "This rule's content type does not support the selected rule ".
            "type."));
      }

      if ($rule->isObjectRule()) {
        $rule->setTriggerObjectPHID($request->getStr('targetPHID'));
        $object = id(new PhabricatorObjectQuery())
          ->setViewer($user)
          ->withPHIDs(array($rule->getTriggerObjectPHID()))
          ->requireCapabilities(
            array(
              PhabricatorPolicyCapability::CAN_VIEW,
              PhabricatorPolicyCapability::CAN_EDIT,
            ))
          ->executeOne();
        if (!$object) {
          throw new Exception(
            pht('No valid object provided for object rule!'));
        }

        if (!$adapter->canTriggerOnObject($object)) {
          throw new Exception(
            pht('Object is of wrong type for adapter!'));
        }
      }

      $cancel_uri = $this->getApplicationURI();
    }

    if ($rule->isGlobalRule()) {
      $this->requireApplicationCapability(
        HeraldManageGlobalRulesCapability::CAPABILITY);
    }

    $adapter = HeraldAdapter::getAdapterForContentType($rule->getContentType());

    $local_version = id(new HeraldRule())->getConfigVersion();
    if ($rule->getConfigVersion() > $local_version) {
      throw new Exception(
        pht(
          'This rule was created with a newer version of Herald. You can not '.
          'view or edit it in this older version. Upgrade your Phabricator '.
          'deployment.'));
    }

    // Upgrade rule version to our version, since we might add newly-defined
    // conditions, etc.
    $rule->setConfigVersion($local_version);

    $rule_conditions = $rule->loadConditions();
    $rule_actions = $rule->loadActions();

    $rule->attachConditions($rule_conditions);
    $rule->attachActions($rule_actions);

    $e_name = true;
    $errors = array();
    if ($request->isFormPost() && $request->getStr('save')) {
      list($e_name, $errors) = $this->saveRule($adapter, $rule, $request);
      if (!$errors) {
        $id = $rule->getID();
        $uri = $this->getApplicationURI("rule/{$id}/");
        return id(new AphrontRedirectResponse())->setURI($uri);
      }
    }

    $must_match_selector = $this->renderMustMatchSelector($rule);
    $repetition_selector = $this->renderRepetitionSelector($rule, $adapter);

    $handles = $this->loadHandlesForRule($rule);

    require_celerity_resource('herald-css');

    $content_type_name = $content_type_map[$rule->getContentType()];
    $rule_type_name = $rule_type_map[$rule->getRuleType()];

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setID('herald-rule-edit-form')
      ->addHiddenInput('content_type', $rule->getContentType())
      ->addHiddenInput('rule_type', $rule->getRuleType())
      ->addHiddenInput('save', 1)
      ->appendChild(
        // Build this explicitly (instead of using addHiddenInput())
        // so we can add a sigil to it.
        javelin_tag(
          'input',
          array(
            'type'  => 'hidden',
            'name'  => 'rule',
            'sigil' => 'rule',
          )))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Rule Name'))
          ->setName('name')
          ->setError($e_name)
          ->setValue($rule->getName()));

    $trigger_object_control = false;
    if ($rule->isObjectRule()) {
      $trigger_object_control = id(new AphrontFormStaticControl())
        ->setValue(
          pht(
            'This rule triggers for %s.',
            $handles[$rule->getTriggerObjectPHID()]->renderLink()));
    }


    $form
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue(pht(
            'This %s rule triggers for %s.',
            phutil_tag('strong', array(), $rule_type_name),
            phutil_tag('strong', array(), $content_type_name))))
      ->appendChild($trigger_object_control)
      ->appendChild(
        id(new PHUIFormInsetView())
          ->setTitle(pht('Conditions'))
          ->setRightButton(javelin_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button green',
              'sigil' => 'create-condition',
              'mustcapture' => true,
            ),
            pht('New Condition')))
          ->setDescription(
            pht('When %s these conditions are met:', $must_match_selector))
          ->setContent(javelin_tag(
            'table',
            array(
              'sigil' => 'rule-conditions',
              'class' => 'herald-condition-table',
            ),
            '')))
      ->appendChild(
        id(new PHUIFormInsetView())
          ->setTitle(pht('Action'))
          ->setRightButton(javelin_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button green',
              'sigil' => 'create-action',
              'mustcapture' => true,
            ),
            pht('New Action')))
          ->setDescription(pht(
            'Take these actions %s this rule matches:',
            $repetition_selector))
          ->setContent(javelin_tag(
              'table',
              array(
                'sigil' => 'rule-actions',
                'class' => 'herald-action-table',
              ),
              '')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Rule'))
          ->addCancelButton($cancel_uri));

    $this->setupEditorBehavior($rule, $handles, $adapter);

    $title = $rule->getID()
        ? pht('Edit Herald Rule')
        : pht('Create Herald Rule');

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb($title);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => pht('Edit Rule'),
      ));
  }

  private function saveRule(HeraldAdapter $adapter, $rule, $request) {
    $rule->setName($request->getStr('name'));
    $match_all = ($request->getStr('must_match') == 'all');
    $rule->setMustMatchAll((int)$match_all);

    $repetition_policy_param = $request->getStr('repetition_policy');
    $rule->setRepetitionPolicy(
      HeraldRepetitionPolicyConfig::toInt($repetition_policy_param));

    $e_name = true;
    $errors = array();

    if (!strlen($rule->getName())) {
      $e_name = pht('Required');
      $errors[] = pht('Rule must have a name.');
    }

    $data = null;
    try {
      $data = phutil_json_decode($request->getStr('rule'));
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('Failed to decode rule data.'),
        $ex);
    }

    if (!is_array($data) ||
        !$data['conditions'] ||
        !$data['actions']) {
      throw new Exception(pht('Failed to decode rule data.'));
    }

    $conditions = array();
    foreach ($data['conditions'] as $condition) {
      if ($condition === null) {
        // We manage this as a sparse array on the client, so may receive
        // NULL if conditions have been removed.
        continue;
      }

      $obj = new HeraldCondition();
      $obj->setFieldName($condition[0]);
      $obj->setFieldCondition($condition[1]);

      if (is_array($condition[2])) {
        $obj->setValue(array_keys($condition[2]));
      } else {
        $obj->setValue($condition[2]);
      }

      try {
        $adapter->willSaveCondition($obj);
      } catch (HeraldInvalidConditionException $ex) {
        $errors[] = $ex->getMessage();
      }

      $conditions[] = $obj;
    }

    $actions = array();
    foreach ($data['actions'] as $action) {
      if ($action === null) {
        // Sparse on the client; removals can give us NULLs.
        continue;
      }

      if (!isset($action[1])) {
        // Legitimate for any action which doesn't need a target, like
        // "Do nothing".
        $action[1] = null;
      }

      $obj = new HeraldAction();
      $obj->setAction($action[0]);
      $obj->setTarget($action[1]);

      try {
        $adapter->willSaveAction($rule, $obj);
      } catch (HeraldInvalidActionException $ex) {
        $errors[] = $ex->getMessage();
      }

      $actions[] = $obj;
    }

    $rule->attachConditions($conditions);
    $rule->attachActions($actions);

    if (!$errors) {
      $edit_action = $rule->getID() ? 'edit' : 'create';

      $rule->openTransaction();
        $rule->save();
        $rule->saveConditions($conditions);
        $rule->saveActions($actions);
      $rule->saveTransaction();
    }

    return array($e_name, $errors);
  }

  private function setupEditorBehavior(
    HeraldRule $rule,
    array $handles,
    HeraldAdapter $adapter) {

    $serial_conditions = array(
      array('default', 'default', ''),
    );

    if ($rule->getConditions()) {
      $serial_conditions = array();
      foreach ($rule->getConditions() as $condition) {
        $value = $condition->getValue();
        switch ($condition->getFieldName()) {
          case HeraldAdapter::FIELD_TASK_PRIORITY:
            $value_map = array();
            $priority_map = ManiphestTaskPriority::getTaskPriorityMap();
            foreach ($value as $priority) {
              $value_map[$priority] = idx($priority_map, $priority);
            }
            $value = $value_map;
            break;
          case HeraldAdapter::FIELD_TASK_STATUS:
            $value_map = array();
            $status_map = ManiphestTaskStatus::getTaskStatusMap();
            foreach ($value as $status) {
              $value_map[$status] = idx($status_map, $status);
            }
            $value = $value_map;
            break;
          default:
            if (is_array($value)) {
              $value_map = array();
              foreach ($value as $k => $fbid) {
                $value_map[$fbid] = $handles[$fbid]->getName();
              }
              $value = $value_map;
            }
            break;
        }
        $serial_conditions[] = array(
          $condition->getFieldName(),
          $condition->getFieldCondition(),
          $value,
        );
      }
    }

    $serial_actions = array(
      array('default', ''),
    );

    if ($rule->getActions()) {
      $serial_actions = array();
      foreach ($rule->getActions() as $action) {
        switch ($action->getAction()) {
          case HeraldAdapter::ACTION_FLAG:
          case HeraldAdapter::ACTION_BLOCK:
            $current_value = $action->getTarget();
            break;
          default:
            if (is_array($action->getTarget())) {
              $target_map = array();
              foreach ((array)$action->getTarget() as $fbid) {
                $target_map[$fbid] = $handles[$fbid]->getName();
              }
              $current_value = $target_map;
            } else {
              $current_value = $action->getTarget();
            }
            break;
        }

        $serial_actions[] = array(
          $action->getAction(),
          $current_value,
        );
      }
    }

    $all_rules = $this->loadRulesThisRuleMayDependUpon($rule);
    $all_rules = mpull($all_rules, 'getName', 'getPHID');
    asort($all_rules);

    $all_fields = $adapter->getFieldNameMap();
    $all_conditions = $adapter->getConditionNameMap();
    $all_actions = $adapter->getActionNameMap($rule->getRuleType());

    $fields = $adapter->getFields();
    $field_map = array_select_keys($all_fields, $fields);

    // Populate any fields which exist in the rule but which we don't know the
    // names of, so that saving a rule without touching anything doesn't change
    // it.
    foreach ($rule->getConditions() as $condition) {
      $field_name = $condition->getFieldName();

      if (empty($field_map[$field_name])) {
        $field_map[$field_name] = pht('<Unknown Field "%s">', $field_name);
      }
    }

    $actions = $adapter->getActions($rule->getRuleType());
    $action_map = array_select_keys($all_actions, $actions);

    // Populate any actions which exist in the rule but which we don't know the
    // names of, so that saving a rule without touching anything doesn't change
    // it.
    foreach ($rule->getActions() as $action) {
      $action_name = $action->getAction();

      if (empty($action_map[$action_name])) {
        $action_map[$action_name] = pht('<Unknown Action "%s">', $action_name);
      }
    }


    $config_info = array();
    $config_info['fields'] = $field_map;
    $config_info['conditions'] = $all_conditions;
    $config_info['actions'] = $action_map;

    foreach ($config_info['fields'] as $field => $name) {
      try {
        $field_conditions = $adapter->getConditionsForField($field);
      } catch (Exception $ex) {
        $field_conditions = array(HeraldAdapter::CONDITION_UNCONDITIONALLY);
      }
      $config_info['conditionMap'][$field] = $field_conditions;
    }

    foreach ($config_info['fields'] as $field => $fname) {
      foreach ($config_info['conditionMap'][$field] as $condition) {
        $value_type = $adapter->getValueTypeForFieldAndCondition(
          $field,
          $condition);
        $config_info['values'][$field][$condition] = $value_type;
      }
    }

    $config_info['rule_type'] = $rule->getRuleType();

    foreach ($config_info['actions'] as $action => $name) {
      try {
        $action_value = $adapter->getValueTypeForAction(
          $action,
         $rule->getRuleType());
      } catch (Exception $ex) {
        $action_value = array(HeraldAdapter::VALUE_NONE);
      }

      $config_info['targets'][$action] = $action_value;
    }

    $changeflag_options =
      PhabricatorRepositoryPushLog::getHeraldChangeFlagConditionOptions();
    Javelin::initBehavior(
      'herald-rule-editor',
      array(
        'root' => 'herald-rule-edit-form',
        'conditions' => (object)$serial_conditions,
        'actions' => (object)$serial_actions,
        'select' => array(
          HeraldAdapter::VALUE_CONTENT_SOURCE => array(
            'options' => PhabricatorContentSource::getSourceNameMap(),
            'default' => PhabricatorContentSource::SOURCE_WEB,
          ),
          HeraldAdapter::VALUE_FLAG_COLOR => array(
            'options' => PhabricatorFlagColor::getColorNameMap(),
            'default' => PhabricatorFlagColor::COLOR_BLUE,
          ),
          HeraldPreCommitRefAdapter::VALUE_REF_TYPE => array(
            'options' => array(
              PhabricatorRepositoryPushLog::REFTYPE_BRANCH
                => pht('branch (git/hg)'),
              PhabricatorRepositoryPushLog::REFTYPE_TAG
                => pht('tag (git)'),
              PhabricatorRepositoryPushLog::REFTYPE_BOOKMARK
                => pht('bookmark (hg)'),
            ),
            'default' => PhabricatorRepositoryPushLog::REFTYPE_BRANCH,
          ),
          HeraldPreCommitRefAdapter::VALUE_REF_CHANGE => array(
            'options' => $changeflag_options,
            'default' => PhabricatorRepositoryPushLog::CHANGEFLAG_ADD,
          ),
        ),
        'template' => $this->buildTokenizerTemplates($handles) + array(
          'rules' => $all_rules,
        ),
        'author' => array(
          $rule->getAuthorPHID() =>
            $handles[$rule->getAuthorPHID()]->getName(),
        ),
        'info' => $config_info,
      ));
  }

  private function loadHandlesForRule($rule) {
    $phids = array();

    foreach ($rule->getActions() as $action) {
      if (!is_array($action->getTarget())) {
        continue;
      }
      foreach ($action->getTarget() as $target) {
        $target = (array)$target;
        foreach ($target as $phid) {
          $phids[] = $phid;
        }
      }
    }

    foreach ($rule->getConditions() as $condition) {
      $value = $condition->getValue();
      if (is_array($value)) {
        foreach ($value as $phid) {
          $phids[] = $phid;
        }
      }
    }

    $phids[] = $rule->getAuthorPHID();

    if ($rule->isObjectRule()) {
      $phids[] = $rule->getTriggerObjectPHID();
    }

    return $this->loadViewerHandles($phids);
  }


  /**
   * Render the selector for the "When (all of | any of) these conditions are
   * met:" element.
   */
  private function renderMustMatchSelector($rule) {
    return AphrontFormSelectControl::renderSelectTag(
      $rule->getMustMatchAll() ? 'all' : 'any',
      array(
        'all' => pht('all of'),
        'any' => pht('any of'),
      ),
      array(
        'name' => 'must_match',
      ));
  }


  /**
   * Render the selector for "Take these actions (every time | only the first
   * time) this rule matches..." element.
   */
  private function renderRepetitionSelector($rule, HeraldAdapter $adapter) {
    $repetition_policy = HeraldRepetitionPolicyConfig::toString(
      $rule->getRepetitionPolicy());

    $repetition_options = $adapter->getRepetitionOptions();
    $repetition_names = HeraldRepetitionPolicyConfig::getMap();
    $repetition_map = array_select_keys($repetition_names, $repetition_options);

    if (count($repetition_map) < 2) {
      return head($repetition_names);
    } else {
      return AphrontFormSelectControl::renderSelectTag(
        $repetition_policy,
        $repetition_map,
        array(
          'name' => 'repetition_policy',
        ));
    }
  }


  protected function buildTokenizerTemplates(array $handles) {
    $template = new AphrontTokenizerTemplateView();
    $template = $template->render();

    $sources = array(
      'repository' => new DiffusionRepositoryDatasource(),
      'legaldocuments' => new LegalpadDocumentDatasource(),
      'taskpriority' => new ManiphestTaskPriorityDatasource(),
      'taskstatus' => new ManiphestTaskStatusDatasource(),
      'buildplan' => new HarbormasterBuildPlanDatasource(),
      'package' => new PhabricatorOwnersPackageDatasource(),
      'project' => new PhabricatorProjectDatasource(),
      'user' => new PhabricatorPeopleDatasource(),
      'email' => new PhabricatorMetaMTAMailableDatasource(),
      'userorproject' => new PhabricatorProjectOrUserDatasource(),
      'applicationemail' => new PhabricatorMetaMTAApplicationEmailDatasource(),
      'space' => new PhabricatorSpacesNamespaceDatasource(),
    );

    foreach ($sources as $key => $source) {
      $source->setViewer($this->getViewer());

      $sources[$key] = array(
        'uri' => $source->getDatasourceURI(),
        'placeholder' => $source->getPlaceholderText(),
        'browseURI' => $source->getBrowseURI(),
      );
    }

    return array(
      'source' => $sources,
      'username' => $this->getRequest()->getUser()->getUserName(),
      'icons' => mpull($handles, 'getTypeIcon', 'getPHID'),
      'markup' => $template,
    );
  }


  /**
   * Load rules for the "Another Herald rule..." condition dropdown, which
   * allows one rule to depend upon the success or failure of another rule.
   */
  private function loadRulesThisRuleMayDependUpon(HeraldRule $rule) {
    $viewer = $this->getRequest()->getUser();

    // Any rule can depend on a global rule.
    $all_rules = id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_GLOBAL))
      ->withContentTypes(array($rule->getContentType()))
      ->execute();

    if ($rule->isObjectRule()) {
      // Object rules may depend on other rules for the same object.
      $all_rules += id(new HeraldRuleQuery())
        ->setViewer($viewer)
        ->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_OBJECT))
        ->withContentTypes(array($rule->getContentType()))
        ->withTriggerObjectPHIDs(array($rule->getTriggerObjectPHID()))
        ->execute();
    }

    if ($rule->isPersonalRule()) {
      // Personal rules may depend upon your other personal rules.
      $all_rules += id(new HeraldRuleQuery())
        ->setViewer($viewer)
        ->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_PERSONAL))
        ->withContentTypes(array($rule->getContentType()))
        ->withAuthorPHIDs(array($rule->getAuthorPHID()))
        ->execute();
    }

    // mark disabled rules as disabled since they are not useful as such;
    // don't filter though to keep edit cases sane / expected
    foreach ($all_rules as $current_rule) {
      if ($current_rule->getIsDisabled()) {
        $current_rule->makeEphemeral();
        $current_rule->setName($rule->getName().' '.pht('(Disabled)'));
      }
    }

    // A rule can not depend upon itself.
    unset($all_rules[$rule->getID()]);

    return $all_rules;
  }

}

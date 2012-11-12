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

    $content_type_map = HeraldContentTypeConfig::getContentTypeMap();
    $rule_type_map = HeraldRuleTypeConfig::getRuleTypeMap();

    if ($this->id) {
      $rule = id(new HeraldRule())->load($this->id);
      if (!$rule) {
        return new Aphront404Response();
      }
      if (!$this->canEditRule($rule, $user)) {
        throw new Exception("You don't own this rule and can't edit it.");
      }
    } else {
      $rule = new HeraldRule();
      $rule->setAuthorPHID($user->getPHID());
      $rule->setMustMatchAll(true);

      $content_type = $request->getStr('content_type');
      if (!isset($content_type_map[$content_type])) {
        $content_type = HeraldContentTypeConfig::CONTENT_TYPE_DIFFERENTIAL;
      }
      $rule->setContentType($content_type);

      $rule_type = $request->getStr('rule_type');
      if (!isset($rule_type_map[$rule_type])) {
        $rule_type = HeraldRuleTypeConfig::RULE_TYPE_GLOBAL;
      }
      $rule->setRuleType($rule_type);
    }

    $local_version = id(new HeraldRule())->getConfigVersion();
    if ($rule->getConfigVersion() > $local_version) {
      throw new Exception(
        "This rule was created with a newer version of Herald. You can not ".
        "view or edit it in this older version. Upgrade your Phabricator ".
        "deployment.");
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
      list($e_name, $errors) = $this->saveRule($rule, $request);
      if (!$errors) {
        $uri = '/herald/view/'.
          $rule->getContentType().'/'.
          $rule->getRuleType().'/';
        return id(new AphrontRedirectResponse())->setURI($uri);
      }
    }

    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    } else {
      $error_view = null;
    }

    $must_match_selector = $this->renderMustMatchSelector($rule);
    $repetition_selector = $this->renderRepetitionSelector($rule);

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
        javelin_render_tag(
          'input',
          array(
            'type'  => 'hidden',
            'name'  => 'rule',
            'sigil' => 'rule',
          )))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Rule Name')
          ->setName('name')
          ->setError($e_name)
          ->setValue($rule->getName()));

    $form
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue(
            "This <strong>${rule_type_name}</strong> rule triggers for " .
            "<strong>${content_type_name}</strong>."))
      ->appendChild(
        id(new AphrontFormInsetView())
          ->setTitle('Conditions')
          ->setRightButton(javelin_render_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button green',
              'sigil' => 'create-condition',
              'mustcapture' => true
            ),
            'Create New Condition'))
          ->setDescription(
            'When '.$must_match_selector .
            ' these conditions are met:')
          ->setContent(javelin_render_tag(
            'table',
            array(
              'sigil' => 'rule-conditions',
              'class' => 'herald-condition-table'
            ),
            '')))
      ->appendChild(
        id(new AphrontFormInsetView())
          ->setTitle('Action')
          ->setRightButton(javelin_render_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button green',
              'sigil' => 'create-action',
              'mustcapture' => true,
            ),
            'Create New Action'))
          ->setDescription('Take these actions '.$repetition_selector.
            ' this rule matches:')
          ->setContent(javelin_render_tag(
              'table',
              array(
                'sigil' => 'rule-actions',
                'class' => 'herald-action-table',
              ),
              '')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save Rule')
          ->addCancelButton('/herald/view/'.$rule->getContentType().'/'));

    $this->setupEditorBehavior($rule, $handles);

    $panel = new AphrontPanelView();
    $panel->setHeader(
      $rule->getID()
        ? 'Edit Herald Rule'
        : 'Create Herald Rule');
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->appendChild($form);

    $nav = $this->renderNav();
    $nav->selectFilter(
      'view/'.$rule->getContentType().'/'.$rule->getRuleType());
    $nav->appendChild(
      array(
        $error_view,
        $panel,
      ));

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Edit Rule',
      ));
  }

  private function canEditRule($rule, $user) {
    return
      ($user->getIsAdmin()) ||
      ($rule->getRuleType() == HeraldRuleTypeConfig::RULE_TYPE_GLOBAL) ||
      ($rule->getAuthorPHID() == $user->getPHID());
  }

  private function saveRule($rule, $request) {
    $rule->setName($request->getStr('name'));
    $rule->setMustMatchAll(($request->getStr('must_match') == 'all'));

    $repetition_policy_param = $request->getStr('repetition_policy');
    $rule->setRepetitionPolicy(
      HeraldRepetitionPolicyConfig::toInt($repetition_policy_param)
    );

    $e_name = true;
    $errors = array();

    if (!strlen($rule->getName())) {
      $e_name = "Required";
      $errors[] = "Rule must have a name.";
    }

    $data = json_decode($request->getStr('rule'), true);
    if (!is_array($data) ||
        !$data['conditions'] ||
        !$data['actions']) {
      throw new Exception("Failed to decode rule data.");
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

      $cond_type = $obj->getFieldCondition();

      if ($cond_type == HeraldConditionConfig::CONDITION_REGEXP) {
        if (@preg_match($obj->getValue(), '') === false) {
          $errors[] =
            'The regular expression "'.$obj->getValue().'" is not valid. '.
            'Regular expressions must have enclosing characters (e.g. '.
            '"@/path/to/file@", not "/path/to/file") and be syntactically '.
            'correct.';
        }
      }

      if ($cond_type == HeraldConditionConfig::CONDITION_REGEXP_PAIR) {
        $json = json_decode($obj->getValue(), true);
        if (!is_array($json)) {
          $errors[] =
            'The regular expression pair "'.$obj->getValue().'" is not '.
            'valid JSON. Enter a valid JSON array with two elements.';
        } else {
          if (count($json) != 2) {
            $errors[] =
              'The regular expression pair "'.$obj->getValue().'" must have '.
              'exactly two elements.';
          } else {
            $key_regexp = array_shift($json);
            $val_regexp = array_shift($json);

            if (@preg_match($key_regexp, '') === false) {
              $errors[] =
                'The first regexp, "'.$key_regexp.'" in the regexp pair '.
                'is not a valid regexp.';
            }
            if (@preg_match($val_regexp, '') === false) {
              $errors[] =
                'The second regexp, "'.$val_regexp.'" in the regexp pair '.
                'is not a valid regexp.';
            }
          }
        }
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

      $actions[] = HeraldActionConfig::willSaveAction($rule->getRuleType(),
                                                      $rule->getAuthorPHID(),
                                                      $action);
    }

    $rule->attachConditions($conditions);
    $rule->attachActions($actions);

    if (!$errors) {
      try {

        $edit_action = $rule->getID() ? 'edit' : 'create';

        $rule->openTransaction();
          $rule->save();
          $rule->saveConditions($conditions);
          $rule->saveActions($actions);
          $rule->logEdit($request->getUser()->getPHID(), $edit_action);
        $rule->saveTransaction();

      } catch (AphrontQueryDuplicateKeyException $ex) {
        $e_name = "Not Unique";
        $errors[] = "Rule name is not unique. Choose a unique name.";
      }
    }

    return array($e_name, $errors);
  }

  private function setupEditorBehavior($rule, $handles) {
    $serial_conditions = array(
      array('default', 'default', ''),
    );

    if ($rule->getConditions()) {
      $serial_conditions = array();
      foreach ($rule->getConditions() as $condition) {

        $value = $condition->getValue();
        if (is_array($value)) {
          $value_map = array();
          foreach ($value as $k => $fbid) {
            $value_map[$fbid] = $handles[$fbid]->getName();
          }
          $value = $value_map;
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
          case HeraldActionConfig::ACTION_FLAG:
            $current_value = $action->getTarget();
            break;
          default:
            $target_map = array();
            foreach ((array)$action->getTarget() as $fbid) {
              $target_map[$fbid] = $handles[$fbid]->getName();
            }
            $current_value = $target_map;
            break;
        }

        $serial_actions[] = array(
          $action->getAction(),
          $current_value,
        );
      }
    }

    $all_rules = $this->loadRulesThisRuleMayDependUpon($rule);
    $all_rules = mpull($all_rules, 'getName', 'getID');
    asort($all_rules);

    $config_info = array();
    $config_info['fields']
      = HeraldFieldConfig::getFieldMapForContentType($rule->getContentType());
    $config_info['conditions'] = HeraldConditionConfig::getConditionMap();
    foreach ($config_info['fields'] as $field => $name) {
      $config_info['conditionMap'][$field] = array_keys(
        HeraldConditionConfig::getConditionMapForField($field));
    }
    foreach ($config_info['fields'] as $field => $fname) {
      foreach ($config_info['conditions'] as $condition => $cname) {
        $config_info['values'][$field][$condition] =
          HeraldValueTypeConfig::getValueTypeForFieldAndCondition(
            $field,
            $condition);
      }
    }

    $config_info['actions'] =
      HeraldActionConfig::getActionMessageMap($rule->getContentType(),
                                              $rule->getRuleType());

    $config_info['rule_type'] = $rule->getRuleType();

    foreach ($config_info['actions'] as $action => $name) {
      $config_info['targets'][$action] =
        HeraldValueTypeConfig::getValueTypeForAction($action,
                                                     $rule->getRuleType());
    }

    Javelin::initBehavior(
      'herald-rule-editor',
      array(
        'root' => 'herald-rule-edit-form',
        'conditions' => (object)$serial_conditions,
        'actions' => (object)$serial_actions,
        'template' => $this->buildTokenizerTemplates() + array(
          'rules' => $all_rules,
          'colors' => PhabricatorFlagColor::getColorNameMap(),
          'defaultColor' => PhabricatorFlagColor::COLOR_BLUE,
        ),
        'author' => array($rule->getAuthorPHID() =>
                          $handles[$rule->getAuthorPHID()]->getName()),
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
        'all' => 'all of',
        'any' => 'any of',
      ),
      array(
        'name' => 'must_match',
      ));
  }


  /**
   * Render the selector for "Take these actions (every time | only the first
   * time) this rule matches..." element.
   */
  private function renderRepetitionSelector($rule) {
    // Make the selector for choosing how often this rule should be repeated
    $repetition_policy = HeraldRepetitionPolicyConfig::toString(
      $rule->getRepetitionPolicy());
    $repetition_options = HeraldRepetitionPolicyConfig::getMapForContentType(
      $rule->getContentType());

    if (empty($repetition_options)) {
      // default option is 'every time'
      $repetition_selector = idx(
        HeraldRepetitionPolicyConfig::getMap(),
        HeraldRepetitionPolicyConfig::EVERY);
      return $repetition_selector;
    } else if (count($repetition_options) == 1) {
      // if there's only 1 option, just pick it for the user
      $repetition_selector = reset($repetition_options);
      return $repetition_selector;
    } else {
      return AphrontFormSelectControl::renderSelectTag(
        $repetition_policy,
        $repetition_options,
        array(
          'name' => 'repetition_policy',
        ));
    }
  }


  protected function buildTokenizerTemplates() {
    $template = new AphrontTokenizerTemplateView();
    $template = $template->render();

    return array(
      'source' => array(
        'email'       => '/typeahead/common/mailable/',
        'user'        => '/typeahead/common/users/',
        'repository'  => '/typeahead/common/repositories/',
        'package'     => '/typeahead/common/packages/',
        'project'     => '/typeahead/common/projects/',
      ),
      'markup' => $template,
    );
  }


  /**
   * Load rules for the "Another Herald rule..." condition dropdown, which
   * allows one rule to depend upon the success or failure of another rule.
   */
  private function loadRulesThisRuleMayDependUpon(HeraldRule $rule) {
    // Any rule can depend on a global rule.
    $all_rules = id(new HeraldRuleQuery())
      ->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_GLOBAL))
      ->withContentTypes(array($rule->getContentType()))
      ->execute();

    if ($rule->getRuleType() == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL) {
      // Personal rules may depend upon your other personal rules.
      $all_rules += id(new HeraldRuleQuery())
        ->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_PERSONAL))
        ->withContentTypes(array($rule->getContentType()))
        ->withAuthorPHIDs(array($rule->getAuthorPHID()))
        ->execute();
    }

    // A rule can not depend upon itself.
    unset($all_rules[$rule->getID()]);

    return $all_rules;
  }


}

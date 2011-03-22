<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class HeraldRuleController extends HeraldController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = (int)idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $content_type_map = HeraldContentTypeConfig::getContentTypeMap();

    if ($this->id) {
      $rule = id(new HeraldRule())->load($this->id);
      if (!$rule) {
        return new Aphront404Response();
      }
      if ($rule->getAuthorPHID() != $user->getPHID()) {
        throw new Exception("You don't own this rule and can't edit it.");
      }
    } else {
      $rule = new HeraldRule();
      $rule->setAuthorPHID($user->getPHID());
      $rule->setMustMatchAll(true);

      $type = $request->getStr('type');
      if (!isset($content_type_map[$type])) {
        $type = HeraldContentTypeConfig::CONTENT_TYPE_DIFFERENTIAL;
      }
      $rule->setContentType($type);
    }

    $local_version = id(new HeraldRule())->getConfigVersion();
    if ($rule->getConfigVersion() > $local_version) {
      throw new Exception(
        "This rule was created with a newer version of Herald. You can not ".
        "view or edit it in this older version. Try dev or wait for a push.");
    }

    // Upgrade rule version to our version, since we might add newly-defined
    // conditions, etc.
    $rule->setConfigVersion($local_version);

    $rule_conditions = $rule->loadConditions();
    $rule_actions = $rule->loadActions();

    $rule->attachConditions($rule_conditions);
    $rule->attachActions($rule_actions);

    $arr = "\xC2\xAB";
    $e_name = true;
    $errors = array();
    if ($request->isFormPost() && $request->getStr('save')) {

      $rule->setName($request->getStr('name'));
      $rule->setMustMatchAll(($request->getStr('must_match') == 'all'));

      if (!strlen($rule->getName())) {
        $e_name = "{$arr} Required";
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
        $obj = new HeraldCondition();
        $obj->setFieldName($condition[0]);
        $obj->setCondition($condition[1]);

        if (is_array($condition[2])) {
          $obj->setValue(array_keys($condition[2]));
        } else {
          $obj->setValue($condition[2]);
        }

        $cond_type = $obj->getCondition();

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
        $obj = new HeraldAction();
        $obj->setAction($action[0]);

        if (!isset($action[1])) {
          // Legitimate for any action which doesn't need a target, like
          // "Do nothing".
          $action[1] = null;
        }

        if (is_array($action[1])) {
          $obj->setTarget(array_keys($action[1]));
        } else {
          $obj->setTarget($action[1]);
        }

        $actions[] = $obj;
      }

      $rule->attachConditions($conditions);
      $rule->attachActions($actions);

      if (!$errors) {
        try {

          $rule->openTransaction();
            $rule->save();
            $rule->saveConditions($conditions);
            $rule->saveActions($actions);
          $rule->saveTransaction();

          $uri = '/herald/view/'.$rule->getContentType().'/';

          return id(new AphrontRedirectResponse())
            ->setURI($uri);
        } catch (QueryDuplicateKeyException $ex) {
          $e_name = "{$arr} Not Unique";
          $errors[] = "Rule name is not unique. Choose a unique name.";
        }
      }

    }

    $phids = array();
    $phids[] = $rule->getAuthorPHID();

    foreach ($rule->getActions() as $action) {
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

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    if ($errors) {
      $errors = '!!';//<tools:error title="Form Errors" errors={$errors} />;
    }


//    require_static('herald-css');


    $options = array(
      'all' => 'all of',
      'any' => 'any of',
    );

    $selected = $rule->getMustMatchAll() ? 'all' : 'any';

    $must_match = array();
    foreach ($options as $key => $option) {
      $must_match[] = phutil_render_tag(
        'option',
        array(
          'selected' => ($selected == $key) ? 'selected' : null,
        ),
        phutil_escape_html($option));
    }
    $must_match =
      '<select name="must_match">'.
        implode("\n", $must_match).
      '</select>';

    if ($rule->getID()) {
      $action = '/herald/rule/'.$rule->getID().'/';
    } else {
      $action = '/herald/rule/'.$rule->getID().'/';
    }

    $type_name = $content_type_map[$rule->getContentType()];

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->addHiddenInput('type', $rule->getContentType())
      ->addHiddenInput('save', true)
      ->addHiddenInput('rule', '')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Rule Name')
          ->setError($e_name)
          ->setValue($rule->getName()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Author')
          ->setValue($handles[$rule->getAuthorPHID()]->getName()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue(
            "This rule triggers for <strong>{$type_name}</strong>."))
      ->appendChild(
        '<h1>Conditions</h1>'.
        '<div style="margin: .5em 0 1em; padding: .5em; background: #aaa;">'.
          '<a href="#" class="button green">Create New Condition</a>'.
          '<p>When '.$must_match.' these conditions are met:</p>'.
          '<table></table>'.
        '</div>')
      ->appendChild(
        '<h1>Action</h1>'.
        '<div style="margin: .5em 0 1em; padding: .5em; background: #aaa;">'.
          '<a href="#" class="button green">Create New Action</a>'.
          '<p>Take these actions:</p>'.
          '<table></table>'.
        '</div>')
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save')
          ->addCancelButton('/herald/view/'.$rule->getContentType().'/'));
/*
    $form =
      <div>
        <tools:form action={URI::getRequestURI()} method="post" width="wide">

          <input type="hidden" name="save" value="true" />
          <input type="hidden" name="rule" value="" sigil="rule" />
          <input type="hidden" name="type" value={$request->getStr('type')} />

          {$errors}

          <h1>Edit Rule</h1>
          <tools:fieldset>

            <tools:control
              type="text"
              label="Rule Name"
              error={$e_name}>
              <input type="text" name="name" value={$rule->getName()} />
            </tools:control>

            <tools:control type="static" label="Owner">
              {$handles[$rule->getAuthorPHID()]->getName()}
            </tools:control>

            <tools:control type="static">
              This rule triggers for
                <strong>{$content_type_map[$rule->getContentType()]}</strong>.
            </tools:control>

          </tools:fieldset>

          <h1 style="margin-top: 1.5em;">Conditions</h1>

          <tools:fieldset>
            <div style="padding: .25em 0 1em 60px;">
              <div style="float: right;">
                <a href="#"
                   sigil="create-condition"
                   class="button green"
                   mustcapture={true}>Create New Condition</a>
              </div>

              When {$must_match} these conditions are met:

            </div>
            <div class="flush" />
            <table class="condition-table" sigil="rule-conditions" />
          </tools:fieldset>


          <h1 style="margin-top: 1.5em;">Actions</h1>
          <tools:fieldset>
            <div style="padding: .25em 0 1.5em 60px;">
              <div style="float: right;">
                <a href="#"
                   sigil="create-action"
                   class="button green"
                   mustcapture={true}>Create New Action</a>
              </div>
              Take these actions:
            </div>
            <div class="flush" />
            <table sigil="rule-actions" class="action-table" />
          </tools:fieldset>

          <div style="margin-top: 0.5em;">
            <tools:control type="submit">
              <button>Save Rule</button>
              <a href="/herald/" class="button grey">Cancel</a>
            </tools:control>
          </div>

        </tools:form>
      </div>;

*/
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
            $value_map[$fbid] = $handles[$fbid]->getExtendedDisplayName();
          }
          $value = $value_map;
        }

        $serial_conditions[] = array(
          $condition->getFieldName(),
          $condition->getCondition(),
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

        $target_map = array();
        foreach ((array)$action->getTarget() as $fbid) {
          $target_map[$fbid] = $handles[$fbid]->getExtendedDisplayName();
        }

        $serial_actions[] = array(
          $action->getAction(),
          $target_map,
        );
      }
    }

    $all_rules = id(new HeraldRule())->loadAllWhere(
      'authorPHID = %d AND contentType = %s',
      $rule->getAuthorPHID(),
      $rule->getContentType());
    $all_rules = mpull($all_rules, 'getName', 'getID');
    asort($all_rules);
    unset($all_rules[$rule->getID()]);


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
      HeraldActionConfig::getActionMapForContentType($rule->getContentType());

    foreach ($config_info['actions'] as $action => $name) {
      $config_info['targets'][$action] =
        HeraldValueTypeConfig::getValueTypeForAction($action);
    }

/*
    Javelin::initBehavior(
      'herald-rule-editor',
      array(
        'root' => 'qq',//$form->requireUniqueId(),
        'conditions' => (object) $serial_conditions,
        'actions' => (object) $serial_actions,
        'template' => $this->buildTokenizerTemplates() + array(
          'rules' => $all_rules,
        ),
        'info' => $config_info,
      ));

*/

    $panel = new AphrontPanelView();
    $panel->setHeader('Edit Herald Rule');
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Edit Rule',
      ));
  }

  protected function buildTokenizerTemplates() {
    return array(
      'source' => array(
        'email'       => '/datasource/mailable/',
        'user'        => '/datasource/user/',
        'repository'  => '/datasource/repository/',
        'tag'         => '/datasource/tag/',
        'package'     => '/datasource/package/',
      ),
      'markup' => 'derp derp',//id(<javelin:tokenizer-template />)->toString(),
    );
  }
}

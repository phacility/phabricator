<?php

final class PhabricatorConfigEditController
  extends PhabricatorConfigController {

  private $key;

  public function willProcessRequest(array $data) {
    $this->key = $data['key'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();


    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    if (empty($options[$this->key])) {
      $ancient = PhabricatorExtraConfigSetupCheck::getAncientConfig();
      if (isset($ancient[$this->key])) {
        $desc = pht(
          "This configuration has been removed. You can safely delete ".
          "it.\n\n%s",
          $ancient[$this->key]);
      } else {
        $desc = pht(
          'This configuration option is unknown. It may be misspelled, '.
          'or have existed in a previous version of Phabricator.');
      }

      // This may be a dead config entry, which existed in the past but no
      // longer exists. Allow it to be edited so it can be reviewed and
      // deleted.
      $option = id(new PhabricatorConfigOption())
        ->setKey($this->key)
        ->setType('wild')
        ->setDefault(null)
        ->setDescription($desc);
      $group = null;
      $group_uri = $this->getApplicationURI();
    } else {
      $option = $options[$this->key];
      $group = $option->getGroup();
      $group_uri = $this->getApplicationURI('group/'.$group->getKey().'/');
    }

    $issue = $request->getStr('issue');
    if ($issue) {
      // If the user came here from an open setup issue, send them back.
      $done_uri = $this->getApplicationURI('issue/'.$issue.'/');
    } else {
      $done_uri = $group_uri;
    }

    // Check if the config key is already stored in the database.
    // Grab the value if it is.
    $config_entry = id(new PhabricatorConfigEntry())
      ->loadOneWhere(
        'configKey = %s AND namespace = %s',
        $this->key,
        'default');
    if (!$config_entry) {
      $config_entry = id(new PhabricatorConfigEntry())
        ->setConfigKey($this->key)
        ->setNamespace('default')
        ->setIsDeleted(true);
      $config_entry->setPHID($config_entry->generatePHID());
    }

    $e_value = null;
    $errors = array();
    if ($request->isFormPost() && !$option->getLocked()) {

      $result = $this->readRequest(
        $option,
        $request);

      list($e_value, $value_errors, $display_value, $xaction) = $result;
      $errors = array_merge($errors, $value_errors);

      if (!$errors) {

        $editor = id(new PhabricatorConfigEditor())
          ->setActor($user)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request);

        try {
          $editor->applyTransactions($config_entry, array($xaction));
          return id(new AphrontRedirectResponse())->setURI($done_uri);
        } catch (PhabricatorConfigValidationException $ex) {
          $e_value = pht('Invalid');
          $errors[] = $ex->getMessage();
        }
      }
    } else {
      if ($config_entry->getIsDeleted()) {
        $display_value = null;
      } else {
        $display_value = $this->getDisplayValue(
          $option,
          $config_entry,
          $config_entry->getValue());
      }
    }

    $form = new AphrontFormView();

    $error_view = null;
    if ($errors) {
      $error_view = id(new PHUIInfoView())
        ->setErrors($errors);
    } else if ($option->getHidden()) {
      $msg = pht(
        'This configuration is hidden and can not be edited or viewed from '.
        'the web interface.');

      $error_view = id(new PHUIInfoView())
        ->setTitle(pht('Configuration Hidden'))
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->appendChild(phutil_tag('p', array(), $msg));
    } else if ($option->getLocked()) {

      $msg = $option->getLockedMessage();
      $error_view = id(new PHUIInfoView())
        ->setTitle(pht('Configuration Locked'))
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(phutil_tag('p', array(), $msg));
    }

    if ($option->getHidden()) {
      $control = null;
    } else {
      $control = $this->renderControl(
        $option,
        $display_value,
        $e_value);
    }

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    $engine->addObject($option, 'description');
    $engine->process();
    $description = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $engine->getOutput($option, 'description'));

    $form
      ->setUser($user)
      ->addHiddenInput('issue', $request->getStr('issue'))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Description'))
          ->setValue($description));

    if ($group) {
      $extra = $group->renderContextualDescription(
        $option,
        $request);
      if ($extra !== null) {
        $form->appendChild(
          id(new AphrontFormMarkupControl())
            ->setValue($extra));
      }
    }

    $form
      ->appendChild($control);

    $submit_control = id(new AphrontFormSubmitControl())
      ->addCancelButton($done_uri);

    if (!$option->getLocked()) {
      $submit_control->setValue(pht('Save Config Entry'));
    }

    $form->appendChild($submit_control);

    $examples = $this->renderExamples($option);
    if ($examples) {
      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Examples'))
          ->setValue($examples));
    }

    if (!$option->getHidden()) {
      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Default'))
          ->setValue($this->renderDefaults($option, $config_entry)));
    }

    $title = pht('Edit %s', $this->key);
    $short = pht('Edit');

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form);

    if ($error_view) {
       $form_box->setInfoView($error_view);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Config'), $this->getApplicationURI());

    if ($group) {
      $crumbs->addTextCrumb($group->getName(), $group_uri);
    }

    $crumbs->addTextCrumb($this->key, '/config/edit/'.$this->key);

    $timeline = $this->buildTransactionTimeline(
      $config_entry,
      new PhabricatorConfigTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function readRequest(
    PhabricatorConfigOption $option,
    AphrontRequest $request) {

    $xaction = new PhabricatorConfigTransaction();
    $xaction->setTransactionType(PhabricatorConfigTransaction::TYPE_EDIT);

    $e_value = null;
    $errors = array();

    $value = $request->getStr('value');
    if (!strlen($value)) {
      $value = null;

      $xaction->setNewValue(
        array(
          'deleted' => true,
          'value'   => null,
        ));

      return array($e_value, $errors, $value, $xaction);
    }

    if ($option->isCustomType()) {
      $info = $option->getCustomObject()->readRequest($option, $request);
      list($e_value, $errors, $set_value, $value) = $info;
    } else {
      $type = $option->getType();
      $set_value = null;

      switch ($type) {
        case 'int':
          if (preg_match('/^-?[0-9]+$/', trim($value))) {
            $set_value = (int)$value;
          } else {
            $e_value = pht('Invalid');
            $errors[] = pht('Value must be an integer.');
          }
          break;
        case 'string':
        case 'enum':
          $set_value = (string)$value;
          break;
        case 'list<string>':
        case 'list<regex>':
          $set_value = phutil_split_lines(
            $request->getStr('value'),
            $retain_endings = false);

          foreach ($set_value as $key => $v) {
            if (!strlen($v)) {
              unset($set_value[$key]);
            }
          }
          $set_value = array_values($set_value);

          break;
        case 'set':
          $set_value = array_fill_keys($request->getStrList('value'), true);
          break;
        case 'bool':
          switch ($value) {
            case 'true':
              $set_value = true;
              break;
            case 'false':
              $set_value = false;
              break;
            default:
              $e_value = pht('Invalid');
              $errors[] = pht('Value must be boolean, "true" or "false".');
              break;
          }
          break;
        case 'class':
          if (!class_exists($value)) {
            $e_value = pht('Invalid');
            $errors[] = pht('Class does not exist.');
          } else {
            $base = $option->getBaseClass();
            if (!is_subclass_of($value, $base)) {
              $e_value = pht('Invalid');
              $errors[] = pht('Class is not of valid type.');
            } else {
              $set_value = $value;
            }
          }
          break;
        default:
          $json = json_decode($value, true);
          if ($json === null && strtolower($value) != 'null') {
            $e_value = pht('Invalid');
            $errors[] = pht(
              'The given value must be valid JSON. This means, among '.
              'other things, that you must wrap strings in double-quotes.');
          } else {
            $set_value = $json;
          }
          break;
      }
    }

    if (!$errors) {
      $xaction->setNewValue(
        array(
          'deleted' => false,
          'value'   => $set_value,
        ));
    } else {
      $xaction = null;
    }

    return array($e_value, $errors, $value, $xaction);
  }

  private function getDisplayValue(
    PhabricatorConfigOption $option,
    PhabricatorConfigEntry $entry,
    $value) {

    if ($option->isCustomType()) {
      return $option->getCustomObject()->getDisplayValue(
        $option,
        $entry,
        $value);
    } else {
      $type = $option->getType();
      switch ($type) {
        case 'int':
        case 'string':
        case 'enum':
        case 'class':
          return $value;
        case 'bool':
          return $value ? 'true' : 'false';
        case 'list<string>':
        case 'list<regex>':
          return implode("\n", nonempty($value, array()));
        case 'set':
          return implode("\n", nonempty(array_keys($value), array()));
        default:
          return PhabricatorConfigJSON::prettyPrintJSON($value);
      }
    }
  }

  private function renderControl(
    PhabricatorConfigOption $option,
    $display_value,
    $e_value) {

    if ($option->isCustomType()) {
      $control = $option->getCustomObject()->renderControl(
        $option,
        $display_value,
        $e_value);
    } else {
      $type = $option->getType();
      switch ($type) {
        case 'int':
        case 'string':
          $control = id(new AphrontFormTextControl());
          break;
        case 'bool':
          $control = id(new AphrontFormSelectControl())
            ->setOptions(
              array(
                ''      => pht('(Use Default)'),
                'true'  => idx($option->getBoolOptions(), 0),
                'false' => idx($option->getBoolOptions(), 1),
              ));
          break;
        case 'enum':
          $options = array_mergev(
            array(
              array('' => pht('(Use Default)')),
              $option->getEnumOptions(),
            ));
          $control = id(new AphrontFormSelectControl())
            ->setOptions($options);
          break;
        case 'class':
          $symbols = id(new PhutilSymbolLoader())
            ->setType('class')
            ->setAncestorClass($option->getBaseClass())
            ->setConcreteOnly(true)
            ->selectSymbolsWithoutLoading();
          $names = ipull($symbols, 'name', 'name');
          asort($names);
          $names = array(
            '' => pht('(Use Default)'),
          ) + $names;

          $control = id(new AphrontFormSelectControl())
            ->setOptions($names);
          break;
        case 'list<string>':
        case 'list<regex>':
          $control = id(new AphrontFormTextAreaControl())
            ->setCaption(pht('Separate values with newlines.'));
          break;
        case 'set':
          $control = id(new AphrontFormTextAreaControl())
            ->setCaption(pht('Separate values with newlines or commas.'));
          break;
        default:
          $control = id(new AphrontFormTextAreaControl())
            ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
            ->setCustomClass('PhabricatorMonospaced')
            ->setCaption(pht('Enter value in JSON.'));
          break;
      }

      $control
        ->setLabel(pht('Value'))
        ->setError($e_value)
        ->setValue($display_value)
        ->setName('value');
    }

    if ($option->getLocked()) {
      $control->setDisabled(true);
    }

    return $control;
  }

  private function renderExamples(PhabricatorConfigOption $option) {
    $examples = $option->getExamples();
    if (!$examples) {
      return null;
    }

    $table = array();
    $table[] = phutil_tag('tr', array('class' => 'column-labels'), array(
      phutil_tag('th', array(), pht('Example')),
      phutil_tag('th', array(), pht('Value')),
    ));
    foreach ($examples as $example) {
      list($value, $description) = $example;

      if ($value === null) {
        $value = phutil_tag('em', array(), pht('(empty)'));
      } else {
        if (is_array($value)) {
          $value = implode("\n", $value);
        }
      }

      $table[] = phutil_tag('tr', array(), array(
        phutil_tag('th', array(), $description),
        phutil_tag('td', array(), $value),
      ));
    }

    require_celerity_resource('config-options-css');

    return phutil_tag(
      'table',
      array(
        'class' => 'config-option-table',
      ),
      $table);
  }

  private function renderDefaults(
    PhabricatorConfigOption $option,
    PhabricatorConfigEntry $entry) {

    $stack = PhabricatorEnv::getConfigSourceStack();
    $stack = $stack->getStack();

    $table = array();
    $table[] = phutil_tag('tr', array('class' => 'column-labels'), array(
      phutil_tag('th', array(), pht('Source')),
      phutil_tag('th', array(), pht('Value')),
    ));
    foreach ($stack as $key => $source) {
      $value = $source->getKeys(
        array(
          $option->getKey(),
        ));

      if (!array_key_exists($option->getKey(), $value)) {
        $value = phutil_tag('em', array(), pht('(empty)'));
      } else {
        $value = $this->getDisplayValue(
          $option,
          $entry,
          $value[$option->getKey()]);
      }

      $table[] = phutil_tag('tr', array('class' => 'column-labels'), array(
        phutil_tag('th', array(), $source->getName()),
        phutil_tag('td', array(), $value),
      ));
    }

    require_celerity_resource('config-options-css');

    return phutil_tag(
      'table',
      array(
        'class' => 'config-option-table',
      ),
      $table);
  }

}

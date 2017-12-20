<?php

final class PhabricatorConfigEditController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $key = $request->getURIData('key');

    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    if (empty($options[$key])) {
      $ancient = PhabricatorExtraConfigSetupCheck::getAncientConfig();
      if (isset($ancient[$key])) {
        $desc = pht(
          "This configuration has been removed. You can safely delete ".
          "it.\n\n%s",
          $ancient[$key]);
      } else {
        $desc = pht(
          'This configuration option is unknown. It may be misspelled, '.
          'or have existed in a previous version of Phabricator.');
      }

      // This may be a dead config entry, which existed in the past but no
      // longer exists. Allow it to be edited so it can be reviewed and
      // deleted.
      $option = id(new PhabricatorConfigOption())
        ->setKey($key)
        ->setType('wild')
        ->setDefault(null)
        ->setDescription($desc);
      $group = null;
      $group_uri = $this->getApplicationURI();
    } else {
      $option = $options[$key];
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
        $key,
        'default');
    if (!$config_entry) {
      $config_entry = id(new PhabricatorConfigEntry())
        ->setConfigKey($key)
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
          ->setActor($viewer)
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

    $form = id(new AphrontFormView())
      ->setEncType('multipart/form-data');

    $error_view = null;
    if ($errors) {
      $error_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_ERROR)
        ->setErrors($errors);
    }

    $doc_href = PhabricatorEnv::getDoclink(
        'Configuration Guide: Locked and Hidden Configuration');

    $doc_link = phutil_tag(
      'a',
      array(
        'href' => $doc_href,
        'target' => '_blank',
      ),
      pht('Learn more about locked and hidden options.'));

    $status_items = array();
    $tag = null;
    if ($option->getHidden()) {
      $tag = id(new PHUITagView())
        ->setName(pht('Hidden'))
        ->setColor(PHUITagView::COLOR_GREY)
        ->setBorder(PHUITagView::BORDER_NONE)
        ->setType(PHUITagView::TYPE_SHADE);

      $message = pht(
        'This configuration is hidden and can not be edited or viewed from '.
        'the web interface.');
      $status_items[] = id(new PHUIInfoView())
        ->appendChild(array($message, ' ', $doc_link));
    } else if ($option->getLocked()) {
      $tag = id(new PHUITagView())
        ->setName(pht('Locked'))
        ->setColor(PHUITagView::COLOR_RED)
        ->setBorder(PHUITagView::BORDER_NONE)
        ->setType(PHUITagView::TYPE_SHADE);

      $message = $option->getLockedMessage();
      $status_items[] = id(new PHUIInfoView())
        ->appendChild(array($message, ' ', $doc_link));
    }

    if ($option->getHidden() || $option->getLocked()) {
      $controls = array();
    } else {
      $controls = $this->renderControls(
        $option,
        $display_value,
        $e_value);
    }

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($viewer);
    $engine->addObject($option, 'description');
    $engine->process();
    $description = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $engine->getOutput($option, 'description'));

    $form
      ->setUser($viewer)
      ->addHiddenInput('issue', $request->getStr('issue'));

    $description = $option->getDescription();
    if (strlen($description)) {
      $description_view = new PHUIRemarkupView($viewer, $description);

      $form
        ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel(pht('Description'))
            ->setValue($description_view));
    }

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

    foreach ($controls as $control) {
      $form->appendControl($control);
    }

    if (!$option->getLocked()) {
      $form->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($done_uri)
          ->setValue(pht('Save Config Entry')));
    }

    $current_config = null;
    if (!$option->getHidden()) {
      $current_config = $this->renderDefaults($option, $config_entry);
      $current_config = $this->buildConfigBoxView(
        pht('Current Configuration'),
        $current_config);
    }

    $examples = $this->renderExamples($option);
    if ($examples) {
      $examples = $this->buildConfigBoxView(
        pht('Examples'),
        $examples);
    }

    $title = $key;

    $box_header = array();
    if ($group) {
      $box_header[] = phutil_tag(
        'a',
        array(
          'href' => $group_uri,
        ),
        $group->getName());
      $box_header[] = " \xC2\xBB ";
    }
    $box_header[] = $key;

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Config'), $this->getApplicationURI());
    if ($group) {
      $crumbs->addTextCrumb($group->getName(), $group_uri);
    }
    $crumbs->addTextCrumb($key, '/config/edit/'.$key);
    $crumbs->setBorder(true);

    $form_box = $this->buildConfigBoxView($box_header, $form, $tag);

    $timeline = $this->buildTransactionTimeline(
      $config_entry,
      new PhabricatorConfigTransactionQuery());
    $timeline->setShouldTerminate(true);

    $nav = $this->buildSideNavView();
    $nav->selectFilter($group_uri);

    $header = $this->buildHeaderView($title);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setNavigation($nav)
      ->setFixed(true)
      ->setMainColumn(array(
        $error_view,
        $form_box,
        $status_items,
        $examples,
        $current_config,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function readRequest(
    PhabricatorConfigOption $option,
    AphrontRequest $request) {

    $type = $option->newOptionType();
    if ($type) {
      $is_set = $type->isValuePresentInRequest($option, $request);
      if ($is_set) {
        $value = $type->readValueFromRequest($option, $request);

        $errors = array();
        try {
          $canonical_value = $type->newValueFromRequestValue(
            $option,
            $value);
          $type->validateStoredValue($option, $canonical_value);
          $xaction = $type->newTransaction($option, $canonical_value);
        } catch (PhabricatorConfigValidationException $ex) {
          $errors[] = $ex->getMessage();
          $xaction = null;
        } catch (Exception $ex) {
          // NOTE: Some older validators throw bare exceptions. Purely in good
          // taste, it would be nice to convert these at some point.
          $errors[] = $ex->getMessage();
          $xaction = null;
        }

        return array(
          $errors ? pht('Invalid') : null,
          $errors,
          $value,
          $xaction,
        );
      } else {
        $delete_xaction = id(new PhabricatorConfigTransaction())
          ->setTransactionType(PhabricatorConfigTransaction::TYPE_EDIT)
          ->setNewValue(
            array(
              'deleted' => true,
              'value' => null,
            ));

        return array(
          null,
          array(),
          null,
          $delete_xaction,
        );
      }
    }

    // TODO: If we missed on the new `PhabricatorConfigType` map, fall back
    // to the old semi-modular, semi-hacky way of doing things.

    $xaction = new PhabricatorConfigTransaction();
    $xaction->setTransactionType(PhabricatorConfigTransaction::TYPE_EDIT);

    $e_value = null;
    $errors = array();

    if ($option->isCustomType()) {
      $info = $option->getCustomObject()->readRequest($option, $request);
      list($e_value, $errors, $set_value, $value) = $info;
    } else {
      throw new Exception(
        pht(
          'Unknown configuration option type "%s".',
          $option->getType()));
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

    $type = $option->newOptionType();
    if ($type) {
      return $type->newDisplayValue($option, $value);
    }

    if ($option->isCustomType()) {
      return $option->getCustomObject()->getDisplayValue(
        $option,
        $entry,
        $value);
    }

    throw new Exception(
      pht(
        'Unknown configuration option type "%s".',
        $option->getType()));
  }

  private function renderControls(
    PhabricatorConfigOption $option,
    $display_value,
    $e_value) {

    $type = $option->newOptionType();
    if ($type) {
      return $type->newControls(
        $option,
        $display_value,
        $e_value);
    }

    if ($option->isCustomType()) {
      $controls = $option->getCustomObject()->renderControls(
        $option,
        $display_value,
        $e_value);
    } else {
      throw new Exception(
        pht(
          'Unknown configuration option type "%s".',
          $option->getType()));
    }

    return $controls;
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

      $table[] = phutil_tag('tr', array('class' => 'column-labels'), array(
        phutil_tag('th', array(), $description),
        phutil_tag('td', array(), $value),
      ));
    }

    require_celerity_resource('config-options-css');

    return phutil_tag(
      'table',
      array(
        'class' => 'config-option-table',
        'cellspacing' => '0',
        'cellpadding' => '0',
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

    $is_effective_value = true;
    foreach ($stack as $key => $source) {
      $row_classes = array(
        'column-labels',
      );

      $value = $source->getKeys(
        array(
          $option->getKey(),
        ));

      if (!array_key_exists($option->getKey(), $value)) {
        $value = phutil_tag('em', array(), pht('(No Value Configured)'));
      } else {
        $value = $this->getDisplayValue(
          $option,
          $entry,
          $value[$option->getKey()]);

        if ($is_effective_value) {
          $is_effective_value = false;
          $row_classes[] = 'config-options-effective-value';
        }
      }

      $table[] = phutil_tag(
        'tr',
        array(
          'class' => implode(' ', $row_classes),
        ),
        array(
          phutil_tag('th', array(), $source->getName()),
          phutil_tag('td', array(), $value),
        ));
    }

    require_celerity_resource('config-options-css');

    return phutil_tag(
      'table',
      array(
        'class' => 'config-option-table',
        'cellspacing' => '0',
        'cellpadding' => '0',
      ),
      $table);
  }

}

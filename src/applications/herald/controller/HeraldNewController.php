<?php

final class HeraldNewController extends HeraldController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $adapter_type_map = HeraldAdapter::getEnabledAdapterMap($viewer);
    $adapter_type = $request->getStr('adapter');

    if (!isset($adapter_type_map[$adapter_type])) {
      $title = pht('Create Herald Rule');
      $content = $this->newAdapterMenu($title);
    } else {
      $adapter = HeraldAdapter::getAdapterForContentType($adapter_type);

      $rule_type_map = HeraldRuleTypeConfig::getRuleTypeMap();
      $rule_type = $request->getStr('type');

      if (!isset($rule_type_map[$rule_type])) {
        $title = pht(
          'Create Herald Rule: %s',
          $adapter->getAdapterContentName());

        $content = $this->newTypeMenu($adapter, $title);
      } else {
        if ($rule_type !== HeraldRuleTypeConfig::RULE_TYPE_OBJECT) {
          $target_phid = null;
          $target_okay = true;
        } else {
          $object_name = $request->getStr('objectName');
          $target_okay = false;

          $errors = array();
          $e_object = null;

          if ($request->isFormPost()) {
            if (strlen($object_name)) {
              $target_object = id(new PhabricatorObjectQuery())
                ->setViewer($viewer)
                ->withNames(array($object_name))
                ->executeOne();
              if ($target_object) {
                $can_edit = PhabricatorPolicyFilter::hasCapability(
                  $viewer,
                  $target_object,
                  PhabricatorPolicyCapability::CAN_EDIT);
                if (!$can_edit) {
                  $errors[] = pht(
                    'You can not create a rule for that object, because you '.
                    'do not have permission to edit it. You can only create '.
                    'rules for objects you can edit.');
                  $e_object = pht('Not Editable');
                } else {
                  if (!$adapter->canTriggerOnObject($target_object)) {
                    $errors[] = pht(
                      'This object is not of an allowed type for the rule. '.
                      'Rules can only trigger on certain objects.');
                    $e_object = pht('Invalid');
                  } else {
                    $target_phid = $target_object->getPHID();
                  }
                }
              } else {
                $errors[] = pht('No object exists by that name.');
                $e_object = pht('Invalid');
              }
            } else {
              $errors[] = pht(
                'You must choose an object to associate this rule with.');
              $e_object = pht('Required');
            }

            $target_okay = !$errors;
          }
        }

        if (!$target_okay) {
          $title = pht('Choose Object');
          $content = $this->newTargetForm(
            $adapter,
            $rule_type,
            $object_name,
            $errors,
            $e_object,
            $title);
        } else {
          $params = array(
            'content_type' => $adapter_type,
            'rule_type' => $rule_type,
            'targetPHID' => $target_phid,
          );

          $edit_uri = $this->getApplicationURI('edit/');
          $edit_uri = new PhutilURI($edit_uri, $params);

          return id(new AphrontRedirectResponse())
            ->setURI($edit_uri);
        }
      }
    }

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Create Rule'))
      ->setBorder(true);

    $view = id(new PHUITwoColumnView())
      ->setFooter($content);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function newAdapterMenu($title) {
    $viewer = $this->getViewer();

    $types = HeraldAdapter::getEnabledAdapterMap($viewer);

    foreach ($types as $key => $type) {
      $types[$key] = HeraldAdapter::getAdapterForContentType($key);
    }

    $types = msort($types, 'getAdapterContentName');

    $base_uri = $this->getApplicationURI('create/');

    $menu = id(new PHUIObjectItemListView())
      ->setViewer($viewer)
      ->setBig(true);

    foreach ($types as $key => $adapter) {
      $adapter_uri = id(new PhutilURI($base_uri))
        ->replaceQueryParam('adapter', $key);

      $description = $adapter->getAdapterContentDescription();
      $description = phutil_escape_html_newlines($description);

      $item = id(new PHUIObjectItemView())
        ->setHeader($adapter->getAdapterContentName())
        ->setImageIcon($adapter->getAdapterContentIcon())
        ->addAttribute($description)
        ->setHref($adapter_uri)
        ->setClickable(true);

      $menu->addItem($item);
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setObjectList($menu);

    return id(new PHUILauncherView())
      ->appendChild($box);
  }

  private function newTypeMenu(HeraldAdapter $adapter, $title) {
    $viewer = $this->getViewer();

    $global_capability = HeraldManageGlobalRulesCapability::CAPABILITY;
    $can_global = $this->hasApplicationCapability($global_capability);

    if ($can_global) {
      $global_note = pht(
        'You have permission to create and manage global rules.');
    } else {
      $global_note = pht(
        'You do not have permission to create or manage global rules.');
    }
    $global_note = phutil_tag('em', array(), $global_note);

    $specs = array(
      HeraldRuleTypeConfig::RULE_TYPE_PERSONAL => array(
        'name' => pht('Personal Rule'),
        'icon' => 'fa-user',
        'help' => pht(
          'Personal rules notify you about events. You own them, but they can '.
          'only affect you. Personal rules only trigger for objects you have '.
          'permission to see.'),
        'enabled' => true,
      ),
      HeraldRuleTypeConfig::RULE_TYPE_OBJECT => array(
        'name' => pht('Object Rule'),
        'icon' => 'fa-cube',
        'help' => pht(
          'Object rules notify anyone about events. They are bound to an '.
          'object (like a repository) and can only act on that object. You '.
          'must be able to edit an object to create object rules for it. '.
          'Other users who can edit the object can edit its rules.'),
        'enabled' => true,
      ),
      HeraldRuleTypeConfig::RULE_TYPE_GLOBAL => array(
        'name' => pht('Global Rule'),
        'icon' => 'fa-globe',
        'help' => array(
          pht(
            'Global rules notify anyone about events. Global rules can '.
            'bypass access control policies and act on any object.'),
          $global_note,
        ),
        'enabled' => $can_global,
      ),
    );

    $adapter_type = $adapter->getAdapterContentType();

    $base_uri = new PhutilURI($this->getApplicationURI('create/'));

    $adapter_uri = id(clone $base_uri)
      ->replaceQueryParam('adapter', $adapter_type);

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setBig(true);

    foreach ($specs as $rule_type => $spec) {
      $type_uri = id(clone $adapter_uri)
        ->replaceQueryParam('type', $rule_type);

      $name = $spec['name'];
      $icon = $spec['icon'];

      $description = $spec['help'];
      $description = (array)$description;

      $enabled = $spec['enabled'];
      if ($enabled) {
        $enabled = $adapter->supportsRuleType($rule_type);
        if (!$enabled) {
          $description[] = phutil_tag(
            'em',
            array(),
            pht(
              'This rule type is not supported by the selected '.
              'content type.'));
        }
      }

      $description = phutil_implode_html(
        array(
          phutil_tag('br'),
          phutil_tag('br'),
        ),
        $description);

      $item = id(new PHUIObjectItemView())
        ->setHeader($name)
        ->setImageIcon($icon)
        ->addAttribute($description);

      if ($enabled) {
        $item
          ->setHref($type_uri)
          ->setClickable(true);
      } else {
        $item->setDisabled(true);
      }

      $menu->addItem($item);
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setObjectList($menu);

    $box->newTailButton()
      ->setText(pht('Back to Content Types'))
      ->setIcon('fa-chevron-left')
      ->setHref($base_uri);

    return id(new PHUILauncherView())
      ->appendChild($box);
  }


  private function newTargetForm(
    HeraldAdapter $adapter,
    $rule_type,
    $object_name,
    $errors,
    $e_object,
    $title) {

    $viewer = $this->getViewer();
    $content_type = $adapter->getAdapterContentType();
    $rule_type_map = HeraldRuleTypeConfig::getRuleTypeMap();

    $params = array(
      'adapter' => $content_type,
      'type' => $rule_type,
    );

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Rule for'))
          ->setValue(
            phutil_tag(
              'strong',
              array(),
              $adapter->getAdapterContentName())))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Rule Type'))
          ->setValue(
            phutil_tag(
              'strong',
              array(),
              idx($rule_type_map, $rule_type))))
      ->appendRemarkupInstructions(
        pht(
          'Choose the object this rule will act on (for example, enter '.
          '`rX` to act on the `rX` repository, or `#project` to act on '.
          'a project).'))
      ->appendRemarkupInstructions(
        $adapter->explainValidTriggerObjects())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('objectName')
          ->setError($e_object)
          ->setValue($object_name)
          ->setLabel(pht('Object')));

    foreach ($params as $key => $value) {
      $form->addHiddenInput($key, $value);
    }

    $cancel_params = $params;
    unset($cancel_params['type']);

    $cancel_uri = $this->getApplicationURI('new/');
    $cancel_uri = new PhutilURI($cancel_uri, $params);

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Continue'))
        ->addCancelButton($cancel_uri, pht('Back')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setForm($form);

    return $form_box;
  }

}

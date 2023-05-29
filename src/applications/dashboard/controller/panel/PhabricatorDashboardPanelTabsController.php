<?php

final class PhabricatorDashboardPanelTabsController
  extends PhabricatorDashboardController {

  private $contextObject;

  private function setContextObject($context_object) {
    $this->contextObject = $context_object;
    return $this;
  }

  private function getContextObject() {
    return $this->contextObject;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $panel = id(new PhabricatorDashboardPanelQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$panel) {
      return new Aphront404Response();
    }

    $tabs_type = id(new PhabricatorDashboardTabsPanelType())
      ->getPanelTypeKey();

    // This controller may only be used to edit tab panels.
    $panel_type = $panel->getPanelType();
    if ($panel_type !== $tabs_type) {
      return new Aphront404Response();
    }

    $op = $request->getURIData('op');
    $after = $request->getStr('after');
    if (!phutil_nonempty_string($after)) {
      $after = null;
    }

    $target = $request->getStr('target');
    if (!phutil_nonempty_string($target)) {
      $target = null;
    }

    $impl = $panel->getImplementation();
    $config = $impl->getPanelConfiguration($panel);

    $cancel_uri = $panel->getURI();

    if ($after !== null) {
      $found = false;
      foreach ($config as $key => $spec) {
        if ((string)$key === $after) {
          $found = true;
          break;
        }
      }

      if (!$found) {
        return $this->newDialog()
          ->setTitle(pht('Adjacent Tab Not Found'))
          ->appendParagraph(
            pht(
              'Adjacent tab ("%s") was not found on this panel. It may have '.
              'been removed.',
              $after))
          ->addCancelButton($cancel_uri);
      }
    }

    if ($target !== null) {
      $found = false;
      foreach ($config as $key => $spec) {
        if ((string)$key === $target) {
          $found = true;
          break;
        }
      }

      if (!$found) {
        return $this->newDialog()
          ->setTitle(pht('Target Tab Not Found'))
          ->appendParagraph(
            pht(
              'Target tab ("%s") was not found on this panel. It may have '.
              'been removed.',
              $target))
          ->addCancelButton($cancel_uri);
      }
    }

    // Tab panels may be edited from the panel page, or from the context of
    // a dashboard. If we're editing from a dashboard, we want to redirect
    // back to the dashboard after making changes.

    $context_phid = $request->getStr('contextPHID');
    $context = null;
    if (phutil_nonempty_string($context_phid)) {
      $context = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($context_phid))
        ->executeOne();
      if (!$context) {
        return new Aphront404Response();
      }

      switch (phid_get_type($context_phid)) {
        case PhabricatorDashboardDashboardPHIDType::TYPECONST:
          $cancel_uri = $context->getURI();
          break;
        case PhabricatorDashboardPanelPHIDType::TYPECONST:
          $cancel_uri = $context->getURI();
          break;
        default:
          return $this->newDialog()
            ->setTitle(pht('Context Object Unsupported'))
            ->appendParagraph(
              pht(
                'Context object ("%s") has unsupported type. Panels should '.
                'be rendered from the context of a dashboard or another '.
                'panel.',
                $context_phid))
            ->addCancelButton($cancel_uri);
      }

      $this->setContextObject($context);
    }

    switch ($op) {
      case 'add':
        return $this->handleAddOperation($panel, $after, $cancel_uri);
      case 'remove':
        return $this->handleRemoveOperation($panel, $target, $cancel_uri);
      case 'move':
        return $this->handleMoveOperation($panel, $target, $after, $cancel_uri);
      case 'rename':
        return $this->handleRenameOperation($panel, $target, $cancel_uri);
    }
  }

  private function handleAddOperation(
    PhabricatorDashboardPanel $panel,
    $after,
    $cancel_uri) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $panel_phid = null;
    $errors = array();
    if ($request->isFormPost()) {
      $panel_phid = $request->getArr('panelPHID');
      $panel_phid = head($panel_phid);

      $add_panel = id(new PhabricatorDashboardPanelQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($panel_phid))
        ->executeOne();
      if (!$add_panel) {
        $errors[] = pht('You must select a valid panel.');
      }

      if (!$errors) {
        $add_panel_config = array(
          'name' => null,
          'panelID' => $add_panel->getID(),
        );
        $add_panel_key = Filesystem::readRandomCharacters(12);

        $impl = $panel->getImplementation();
        $old_config = $impl->getPanelConfiguration($panel);
        $new_config = array();
        if ($after === null) {
          $new_config = $old_config;
          $new_config[] = $add_panel_config;
        } else {
          foreach ($old_config as $key => $value) {
            $new_config[$key] = $value;
            if ((string)$key === $after) {
              $new_config[$add_panel_key] = $add_panel_config;
            }
          }
        }

        $xactions = array();

        $xactions[] = $panel->getApplicationTransactionTemplate()
          ->setTransactionType(
            PhabricatorDashboardTabsPanelTabsTransaction::TRANSACTIONTYPE)
          ->setNewValue($new_config);

        $editor = id(new PhabricatorDashboardPanelTransactionEditor())
          ->setContentSourceFromRequest($request)
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
          ->setContinueOnMissingFields(true);

        $editor->applyTransactions($panel, $xactions);

        return id(new AphrontRedirectResponse())->setURI($cancel_uri);
      }
    }

    if ($panel_phid) {
      $v_panel = array($panel_phid);
    } else {
      $v_panel = array();
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorDashboardPanelDatasource())
          ->setLimit(1)
          ->setName('panelPHID')
          ->setLabel(pht('Panel'))
          ->setValue($v_panel));

    return $this->newEditDialog()
      ->setTitle(pht('Choose Dashboard Panel'))
      ->setErrors($errors)
      ->addHiddenInput('after', $after)
      ->appendForm($form)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Add Panel'));
  }

  private function handleRemoveOperation(
    PhabricatorDashboardPanel $panel,
    $target,
    $cancel_uri) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $panel_phid = null;
    $errors = array();
    if ($request->isFormPost()) {
      $impl = $panel->getImplementation();
      $old_config = $impl->getPanelConfiguration($panel);

      $new_config = $this->removePanel($old_config, $target);
      $this->writePanelConfig($panel, $new_config);

      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    return $this->newEditDialog()
      ->setTitle(pht('Remove tab?'))
      ->addHiddenInput('target', $target)
      ->appendParagraph(pht('Really remove this tab?'))
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Remove Tab'));
  }

  private function handleRenameOperation(
    PhabricatorDashboardPanel $panel,
    $target,
    $cancel_uri) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $impl = $panel->getImplementation();
    $old_config = $impl->getPanelConfiguration($panel);

    $spec = $old_config[$target];
    $name = idx($spec, 'name');

    if ($request->isFormPost()) {
      $name = $request->getStr('name');

      $new_config = $this->renamePanel($old_config, $target, $name);
      $this->writePanelConfig($panel, $new_config);

      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendControl(
        id(new AphrontFormTextControl())
          ->setValue($name)
          ->setName('name')
          ->setLabel(pht('Tab Name')));

    return $this->newEditDialog()
      ->setTitle(pht('Rename Panel'))
      ->addHiddenInput('target', $target)
      ->appendForm($form)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Rename Tab'));
  }

  private function handleMoveOperation(
    PhabricatorDashboardPanel $panel,
    $target,
    $after,
    $cancel_uri) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $move = $request->getStr('move');

    $impl = $panel->getImplementation();
    $old_config = $impl->getPanelConfiguration($panel);

    $is_next = ($move === 'next');
    if ($target === $after) {
      return $this->newDialog()
        ->setTitle(pht('Impossible!'))
        ->appendParagraph(
          pht(
            'You can not move a tab relative to itself.'))
        ->addCancelButton($cancel_uri);
    } else if ($is_next && ((string)last_key($old_config) === $target)) {
      return $this->newDialog()
        ->setTitle(pht('Impossible!'))
        ->appendParagraph(
          pht(
            'This is already the last tab. It can not move any farther to '.
            'the right.'))
        ->addCancelButton($cancel_uri);
    } else if ((string)head_key($old_config) === $target) {
      return $this->newDialog()
        ->setTitle(pht('Impossible!'))
        ->appendParagraph(
          pht(
            'This is already the first tab. It can not move any farther to '.
            'the left.'))
        ->addCancelButton($cancel_uri);
    }

    if ($request->hasCSRF()) {
      $new_config = array();
      foreach ($old_config as $old_key => $old_spec) {
        $old_key = (string)$old_key;

        $is_after = ($old_key === $after);

        if (!$is_after) {
          if ($old_key === $target) {
            continue;
          }
        }

        if ($is_after && !$is_next) {
          $new_config[$target] = $old_config[$target];
        }

        $new_config[$old_key] = $old_spec;

        if ($is_after && $is_next) {
          $new_config[$target] = $old_config[$target];
        }
      }

      $this->writePanelConfig($panel, $new_config);

      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    if ($is_next) {
      $prompt = pht('Move this tab to the right?');
    } else {
      $prompt = pht('Move this tab to the left?');
    }

    return $this->newEditDialog()
      ->setTitle(pht('Move Tab'))
      ->addHiddenInput('target', $target)
      ->addHiddenInput('after', $after)
      ->addHiddenInput('move', $move)
      ->appendParagraph($prompt)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Move Tab'));
  }

  private function writePanelConfig(
    PhabricatorDashboardPanel $panel,
    array $config) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $xactions = array();

    $xactions[] = $panel->getApplicationTransactionTemplate()
      ->setTransactionType(
        PhabricatorDashboardTabsPanelTabsTransaction::TRANSACTIONTYPE)
      ->setNewValue($config);

    $editor = id(new PhabricatorDashboardPanelTransactionEditor())
      ->setContentSourceFromRequest($request)
      ->setActor($viewer)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    return $editor->applyTransactions($panel, $xactions);
  }

  private function removePanel(array $config, $target) {
    $result = array();

    foreach ($config as $key => $panel_spec) {
      if ((string)$key === $target) {
        continue;
      }
      $result[$key] = $panel_spec;
    }

    return $result;
  }

  private function renamePanel(array $config, $target, $name) {
    $config[$target]['name'] = $name;
    return $config;
  }

  protected function newEditDialog() {
    $dialog = $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FORM);

    $context = $this->getContextObject();
    if ($context) {
      $dialog->addHiddenInput('contextPHID', $context->getPHID());
    }

    return $dialog;
  }

}

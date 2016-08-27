<?php

final class PhabricatorHomePreferencesSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'home';
  }

  public function getPanelName() {
    return pht('Home Page');
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsApplicationsPanelGroup::PANELGROUPKEY;
  }

  public function isTemplatePanel() {
    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $preferences = $this->getPreferences();

    $pinned_key = PhabricatorPinnedApplicationsSetting::SETTINGKEY;
    $pinned = $preferences->getSettingValue($pinned_key);

    $apps = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withInstalled(true)
      ->withUnlisted(false)
      ->withLaunchable(true)
      ->execute();

    $app_list = array();
    foreach ($pinned as $app) {
      if (isset($apps[$app])) {
        $app_list[$app] = $apps[$app];
      }
    }

    if ($request->getBool('reset')) {
        if ($request->isFormPost()) {
          $this->writePinnedApplications($preferences, null);
          return id(new AphrontRedirectResponse())
            ->setURI($this->getPanelURI());
        }

        return $this->newDialog()
          ->setTitle(pht('Reset Applications'))
          ->addHiddenInput('reset', 'true')
          ->appendParagraph(
            pht('Reset pinned applications to their defaults?'))
          ->addSubmitButton(pht('Reset Applications'))
          ->addCancelButton($this->getPanelURI());
      }


    if ($request->getBool('add')) {
      $options = array();
      foreach ($apps as $app) {
        $options[get_class($app)] = $app->getName();
      }
      asort($options);

      unset($options['PhabricatorApplicationsApplication']);

      if ($request->isFormPost()) {
        $pins = $request->getArr('pin');
        $phid = head($pins);
        $app = id(new PhabricatorApplicationQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($phid))
          ->executeOne();
        if ($app) {
          $pin = get_class($app);
        } else {
          // This likely means the user submitted an empty form
          // which will cause nothing to happen.
          $pin = '';
        }
        if (isset($options[$pin]) && !in_array($pin, $pinned)) {
          $pinned[] = $pin;

          $this->writePinnedApplications($preferences, $pinned);

          return id(new AphrontRedirectResponse())
            ->setURI($this->getPanelURI());
        }
      }

      $options_control = id(new AphrontFormTokenizerControl())
        ->setName('pin')
        ->setLabel(pht('Application'))
        ->setDatasource(new PhabricatorApplicationDatasource())
        ->setLimit(1);

      $form = id(new AphrontFormView())
        ->setViewer($viewer)
        ->addHiddenInput('add', 'true')
        ->appendRemarkupInstructions(
          pht('Choose an application to pin to your home page.'))
        ->appendControl($options_control);

      return $this->newDialog()
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setTitle(pht('Pin Application'))
        ->appendChild($form->buildLayoutView())
        ->addSubmitButton(pht('Pin Application'))
        ->addCancelButton($this->getPanelURI());
    }

    $unpin = $request->getStr('unpin');
    if ($unpin) {
      $app = idx($apps, $unpin);
      if ($app) {
        if ($request->isFormPost()) {
          $pinned = array_diff($pinned, array($unpin));

          $this->writePinnedApplications($preferences, $pinned);

          return id(new AphrontRedirectResponse())
            ->setURI($this->getPanelURI());
        }

        return $this->newDialog()
          ->setTitle(pht('Unpin Application'))
          ->addHiddenInput('unpin', $unpin)
          ->appendParagraph(
            pht(
              'Unpin the %s application from your home page?',
              phutil_tag('strong', array(), $app->getName())))
          ->addSubmitButton(pht('Unpin Application'))
          ->addCancelButton($this->getPanelURI());
      }
    }

    $order = $request->getStrList('order');
    if ($order && $request->validateCSRF()) {
      $this->writePinnedApplications($preferences, $order);

      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI());
    }

    $list_id = celerity_generate_unique_node_id();

    $list = id(new PHUIObjectItemListView())
      ->setViewer($viewer)
      ->setID($list_id);

    Javelin::initBehavior(
      'reorder-applications',
      array(
        'listID' => $list_id,
        'panelURI' => $this->getPanelURI(),
      ));

    foreach ($app_list as $key => $application) {
      if ($key == 'PhabricatorApplicationsApplication') {
        continue;
      }

      $icon = $application->getIcon();
      if (!$icon) {
        $icon = 'fa-globe';
      }

      $item = id(new PHUIObjectItemView())
        ->setHeader($application->getName())
        ->setImageIcon($icon)
        ->addAttribute($application->getShortDescription())
        ->setGrippable(true);

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('fa-times')
          ->setHref($this->getPanelURI().'?unpin='.$key)
          ->setWorkflow(true));

      $item->addSigil('pinned-application');
      $item->setMetadata(
        array(
          'applicationClass' => $key,
        ));

      $list->addItem($item);
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Pinned Applications'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setText(pht('Pin Application'))
          ->setHref($this->getPanelURI().'?add=true')
          ->setWorkflow(true)
          ->setIcon('fa-thumb-tack'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setText(pht('Reset to Defaults'))
          ->setHref($this->getPanelURI().'?reset=true')
          ->setWorkflow(true)
          ->setIcon('fa-recycle'));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setObjectList($list);

    return $box;
  }

  private function writePinnedApplications(
    PhabricatorUserPreferences $preferences,
    $pinned) {

    $pinned_key = PhabricatorPinnedApplicationsSetting::SETTINGKEY;
    $this->writeSetting($preferences, $pinned_key, $pinned);
  }

}

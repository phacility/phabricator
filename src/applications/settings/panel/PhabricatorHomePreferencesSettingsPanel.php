<?php

final class PhabricatorHomePreferencesSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'home';
  }

  public function getPanelName() {
    return pht('Home Page');
  }

  public function getPanelGroup() {
    return pht('Application Settings');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $preferences = $user->loadPreferences();

    $apps = id(new PhabricatorApplicationQuery())
      ->setViewer($user)
      ->withInstalled(true)
      ->withUnlisted(false)
      ->withLaunchable(true)
      ->execute();

    $pinned = $preferences->getPinnedApplications($apps, $user);

    $app_list = array();
    foreach ($pinned as $app) {
      if (isset($apps[$app])) {
        $app_list[$app] = $apps[$app];
      }
    }

    if ($request->getBool('add')) {
      $options = array();
      foreach ($apps as $app) {
        $options[get_class($app)] = $app->getName();
      }
      asort($options);

      unset($options['PhabricatorApplicationsApplication']);

      if ($request->isFormPost()) {
        $pin = $request->getStr('pin');
        if (isset($options[$pin]) && !in_array($pin, $pinned)) {
          $pinned[] = $pin;
          $preferences->setPreference(
            PhabricatorUserPreferences::PREFERENCE_APP_PINNED,
            $pinned);
          $preferences->save();

          return id(new AphrontRedirectResponse())
            ->setURI($this->getPanelURI());
        }
      }

      $options_control = id(new AphrontFormSelectControl())
        ->setName('pin')
        ->setLabel(pht('Application'))
        ->setOptions($options)
        ->setDisabledOptions(array_keys($app_list));

      $form = id(new AphrontFormView())
        ->setUser($user)
        ->addHiddenInput('add', 'true')
        ->appendRemarkupInstructions(
          pht('Choose an application to pin to your home page.'))
        ->appendChild($options_control);

      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setTitle(pht('Pin Application'))
        ->appendChild($form->buildLayoutView())
        ->addSubmitButton(pht('Pin Application'))
        ->addCancelButton($this->getPanelURI());

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $unpin = $request->getStr('unpin');
    if ($unpin) {
      $app = idx($apps, $unpin);
      if ($app) {
        if ($request->isFormPost()) {
          $pinned = array_diff($pinned, array($unpin));
          $preferences->setPreference(
            PhabricatorUserPreferences::PREFERENCE_APP_PINNED,
            $pinned);
          $preferences->save();

          return id(new AphrontRedirectResponse())
            ->setURI($this->getPanelURI());
        }

        $dialog = id(new AphrontDialogView())
          ->setUser($user)
          ->setTitle(pht('Unpin Application'))
          ->appendParagraph(
            pht(
              'Unpin the %s application from your home page?',
              phutil_tag('strong', array(), $app->getName())))
          ->addSubmitButton(pht('Unpin Application'))
          ->addCanceLButton($this->getPanelURI());

        return id(new AphrontDialogResponse())->setDialog($dialog);
      }
    }

    $order = $request->getStrList('order');
    if ($order && $request->validateCSRF()) {
      $preferences->setPreference(
        PhabricatorUserPreferences::PREFERENCE_APP_PINNED,
        $order);
      $preferences->save();

      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI());
    }

    $list_id = celerity_generate_unique_node_id();

    $list = id(new PHUIObjectItemListView())
      ->setUser($user)
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

      $icon = $application->getFontIcon();
      if (!$icon) {
        $icon = 'application';
      }

      $icon_view = javelin_tag(
        'span',
        array(
          'class' => 'phui-icon-view phui-font-fa '.$icon,
          'aural' => false,
        ),
        '');

      $item = id(new PHUIObjectItemView())
        ->setHeader($application->getName())
        ->setImageIcon($icon_view)
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
          ->setIcon(
            id(new PHUIIconView())
              ->setIconFont('fa-thumb-tack')));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setObjectList($list);

    return $box;
  }

}

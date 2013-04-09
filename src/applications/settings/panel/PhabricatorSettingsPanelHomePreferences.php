<?php

final class PhabricatorSettingsPanelHomePreferences
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

    require_celerity_resource('phabricator-settings-css');

    $apps = PhabricatorApplication::getAllInstalledApplications();
    $pref_tiles = PhabricatorUserPreferences::PREFERENCE_APP_TILES;
    $tiles = $preferences->getPreference($pref_tiles, array());

    if ($request->isFormPost()) {
      $values = $request->getArr('tile');
      foreach ($apps as $app) {
        $key = get_class($app);
        $value = idx($values, $key);
        switch ($value) {
          case PhabricatorApplication::TILE_FULL:
          case PhabricatorApplication::TILE_SHOW:
          case PhabricatorApplication::TILE_HIDE:
            $tiles[$key] = $value;
            break;
          default:
            unset($tiles[$key]);
            break;
        }
      }
      $preferences->setPreference($pref_tiles, $tiles);
      $preferences->save();

      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?saved=true'));
    }

    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Home Page Preferences'));

    $form = id(new AphrontFormView())
      ->setFlexible(true)
      ->setUser($user);

    $group_map = PhabricatorApplication::getApplicationGroups();

    $output = array();

    $applications = PhabricatorApplication::getAllInstalledApplications();

    $applications = mgroup($applications, 'getApplicationGroup');

    $applications = array_select_keys(
    $applications,
    array_keys($group_map));

    foreach ($applications as $group => $apps) {
      $group_name = $group_map[$group];
      $rows = array();

      foreach ($apps as $app) {
        if (!$app->shouldAppearInLaunchView()) {
        continue;
        }

        $default = $app->getDefaultTileDisplay($user);
        if ($default == PhabricatorApplication::TILE_INVISIBLE) {
          continue;
        }



        $default_name = PhabricatorApplication::getTileDisplayName($default);

        $hide = PhabricatorApplication::TILE_HIDE;
        $show = PhabricatorApplication::TILE_SHOW;
        $full = PhabricatorApplication::TILE_FULL;

        $key = get_class($app);

        $default_radio_button_status =
          (idx($tiles, $key, 'default') == 'default') ? 'checked' : null;

        $hide_radio_button_status =
          (idx($tiles, $key, 'default') == $hide) ? 'checked' : null;

        $show_radio_button_status =
          (idx($tiles, $key, 'default') == $show) ? 'checked' : null;

        $full_radio_button_status =
          (idx($tiles, $key, 'default') == $full) ? 'checked' : null;


        $default_radio_button = phutil_tag(
          'input',
          array(
            'type' => 'radio',
            'name' => 'tile['.$key.']',
            'value' => 'default',
            'checked' => $default_radio_button_status,
          ));

        $hide_radio_button = phutil_tag(
          'input',
          array(
            'type' => 'radio',
            'name' => 'tile['.$key.']',
            'value' => $hide,
            'checked' => $hide_radio_button_status,
          ));

        $show_radio_button = phutil_tag(
          'input',
          array(
            'type' => 'radio',
            'name' => 'tile['.$key.']',
            'value' => $show,
            'checked' => $show_radio_button_status,
          ));

        $full_radio_button = phutil_tag(
          'input',
          array(
            'type' => 'radio',
            'name' => 'tile['.$key.']',
            'value' => $full,
            'checked' => $full_radio_button_status,
          ));

        $app_column = hsprintf(
                        "<strong>%s</strong><br /><em> Default: %s</em>"
                        , $app->getName(), $default_name);

        $rows[] = array(
          $app_column,
          $default_radio_button,
          $hide_radio_button,
          $show_radio_button,
          $full_radio_button,
          );
      }

      if (empty($rows)) {
        continue;
      }

      $table = new AphrontTableView($rows);

      $table
        ->setClassName('phabricator-settings-homepagetable')
        ->setHeaders(
          array(
            pht('Applications'),
            pht('Default'),
            pht('Hidden'),
            pht('Small'),
            pht('Large'),
            ))
        ->setColumnClasses(
          array(
            '',
            'fixed',
            'fixed',
            'fixed',
            'fixed',
          ));


      $panel = id(new AphrontPanelView())
                 ->setHeader($group_name)
                 ->addClass('phabricator-settings-panelview')
                 ->appendChild($table)
                 ->setNoBackground();


      $output[] = $panel;

    }

    $form
      ->appendChild($output)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Preferences')));

    $error_view = null;
    if ($request->getStr('saved') === 'true') {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Preferences Saved'))
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setErrors(array(pht('Your preferences have been saved.')));
    }

    return array(
      $header,
      $error_view,
      $form,
    );
  }
}


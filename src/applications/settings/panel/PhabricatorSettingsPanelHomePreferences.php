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

    $apps = msort($apps, 'getName');
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
      // Won't pht() for dynamic string (Applcation Name)
      $form->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel($app->getName())
          ->setName('tile['.$key.']')
          ->setOptions(
            array(
              $hide     => PhabricatorApplication::getTileDisplayName($hide),
              'default' => pht('Use Default (%s)', $default_name),
              $show     => PhabricatorApplication::getTileDisplayName($show),
              $full     => PhabricatorApplication::getTileDisplayName($full),
            ))
          ->setValue(idx($tiles, $key, 'default')));
    }

    $form
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


<?php

final class PhabricatorSettingsListController
  extends PhabricatorController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    // If the viewer isn't an administrator, just redirect them to their own
    // settings panel.
    if (!$viewer->getIsAdmin()) {
      $settings_uri = '/user/'.$viewer->getUsername().'/';
      $settings_uri = $this->getApplicationURI($settings_uri);
      return id(new AphrontRedirectResponse())
        ->setURI($settings_uri);
    }

    return id(new PhabricatorUserPreferencesSearchEngine())
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $viewer = $this->getViewer();
    if ($viewer->getIsAdmin()) {
      $builtin_global = PhabricatorUserPreferences::BUILTIN_GLOBAL_DEFAULT;
      $global_settings = id(new PhabricatorUserPreferencesQuery())
        ->setViewer($viewer)
        ->withBuiltinKeys(
          array(
            $builtin_global,
          ))
        ->execute();
      if (!$global_settings) {
        $action = id(new PHUIListItemView())
          ->setName(pht('Create Global Defaults'))
          ->setHref('/settings/builtin/'.$builtin_global.'/')
          ->setIcon('fa-plus');
        $crumbs->addAction($action);
      }
    }

    return $crumbs;
  }

}

<?php

abstract class PhabricatorConfigSettingsController
  extends PhabricatorConfigController {

  public function newNavigation($select_filter) {
    $settings_uri = $this->getApplicationURI('settings/');

    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI(new PhutilURI($settings_uri));

    $nav->addLabel(pht('Configuration'));

    $nav->newLink('settings')
      ->setName(pht('Core Settings'))
      ->setIcon('fa-wrench')
      ->setHref($settings_uri);

    $nav->newLink('advanced')
      ->setName(pht('Advanced Settings'))
      ->setIcon('fa-cogs')
      ->setHref(urisprintf('%s%s/', $settings_uri, 'advanced'));

    $nav->newLink('all')
      ->setName(pht('All Settings'))
      ->setIcon('fa-list')
      ->setHref(urisprintf('%s%s/', $settings_uri, 'all'));

    $nav->addLabel(pht('History'));

    $nav->newLink('history')
      ->setName(pht('View History'))
      ->setIcon('fa-history')
      ->setHref(urisprintf('%s%s/', $settings_uri, 'history'));

    if ($select_filter) {
      $nav->selectFilter($select_filter);
    }

    return $nav;
  }

  public function newCrumbs() {
    $settings_uri = $this->getApplicationURI('settings/');

    return $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Settings'), $settings_uri)
      ->setBorder(true);
  }

}

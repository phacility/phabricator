<?php

final class PhabricatorApplicationApplications extends PhabricatorApplication {

  public function getBaseURI() {
    return '/applications/';
  }

  public function getShortDescription() {
    return 'Installed Applications';
  }

  public function getIconName() {
    return 'applications';
  }

  public function getTitleGlyph() {
    return "\xE0\xBC\x84";
  }

  public function shouldAppearInLaunchView() {
    return false;
  }

 public function getRoutes() {
    return array(
      '/applications/' => array(
        ''                          => 'PhabricatorApplicationsListController',

        ),

    );
  }

}


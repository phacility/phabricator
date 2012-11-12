<?php

final class PhabricatorApplicationApplications extends PhabricatorApplication {

  public function getBaseURI() {
    return '/applications/';
  }

  public function getShortDescription() {
    return 'Manage Applications';
  }

  public function getAutospriteName() {
    return 'applications';
  }

  public function getRoutes() {
    return array(
      '/applications/' => array(
        '' => 'PhabricatorApplicationsListController'
      ),
    );
  }

  public function getTitleGlyph() {
    return "\xE0\xBC\x84";
  }

  public function shouldAppearInLaunchView() {
    return false;
  }

}


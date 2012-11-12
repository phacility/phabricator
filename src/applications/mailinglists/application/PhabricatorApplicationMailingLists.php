<?php

final class PhabricatorApplicationMailingLists extends PhabricatorApplication {

  public function getName() {
    return 'Mailing Lists';
  }

  public function getBaseURI() {
    return '/mailinglists/';
  }

  public function getShortDescription() {
    return 'Manage External Lists';
  }

  public function getAutospriteName() {
    return 'mail';
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function getRoutes() {
    return array(
      '/mailinglists/' => array(
        '' => 'PhabricatorMailingListsListController',
        'edit/(?:(?P<id>[1-9]\d*)/)?'
          => 'PhabricatorMailingListsEditController',
      ),
    );
  }

  public function getTitleGlyph() {
    return '@';
  }

}

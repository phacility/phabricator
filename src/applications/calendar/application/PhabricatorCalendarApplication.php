<?php

final class PhabricatorCalendarApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Calendar');
  }

  public function getShortDescription() {
    return pht('Upcoming Events');
  }

  public function getFlavorText() {
    return pht('Never miss an episode ever again.');
  }

  public function getBaseURI() {
    return '/calendar/';
  }

  public function getIconName() {
    return 'calendar';
  }

  public function getTitleGlyph() {
    // Unicode has a calendar character but it's in some distant code plane,
    // use "keyboard" since it looks vaguely similar.
    return "\xE2\x8C\xA8";
  }

  public function isBeta() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/calendar/' => array(
        '' => 'PhabricatorCalendarViewController',
        'all/' => 'PhabricatorCalendarBrowseController',
        'event/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?'
            => 'PhabricatorCalendarEventListController',
          'create/'
            => 'PhabricatorCalendarEventEditController',
          'edit/(?P<id>[1-9]\d*)/'
            => 'PhabricatorCalendarEventEditController',
          'delete/(?P<id>[1-9]\d*)/'
            => 'PhabricatorCalendarEventDeleteController',
          'view/(?P<id>[1-9]\d*)/'
            => 'PhabricatorCalendarEventViewController',
        ),
      ),
    );
  }

  public function getQuickCreateItems(PhabricatorUser $viewer) {
    $items = array();

    $item = id(new PHUIListItemView())
      ->setName(pht('Calendar Event'))
      ->setIcon('fa-calendar')
      ->setHref($this->getBaseURI().'event/create/');
    $items[] = $item;

    return $items;
  }

}

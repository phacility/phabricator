<?php

final class PhabricatorConpherenceApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/conpherence/';
  }

  public function getName() {
    return pht('Conpherence');
  }

  public function getShortDescription() {
    return pht('Send Messages');
  }

  public function getFontIcon() {
    return 'fa-comments';
  }

  public function getTitleGlyph() {
    return "\xE2\x9C\x86";
  }

  public function getEventListeners() {
    return array(
      new ConpherenceHovercardEventListener(),
    );
  }

  public function getRoutes() {
    return array(
      '/conpherence/' => array(
        ''                         => 'ConpherenceListController',
        'thread/(?P<id>[1-9]\d*)/' => 'ConpherenceListController',
        '(?P<id>[1-9]\d*)/'        => 'ConpherenceViewController',
        'columnview/'              => 'ConpherenceColumnViewController',
        'new/'                     => 'ConpherenceNewController',
        'room/'                    => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'ConpherenceRoomListController',
          'new/'                            => 'ConpherenceNewRoomController',
        ),
        'panel/'                   => 'ConpherenceNotificationPanelController',
        'widget/(?P<id>[1-9]\d*)/' => 'ConpherenceWidgetController',
        'update/(?P<id>[1-9]\d*)/' => 'ConpherenceUpdateController',
      ),
    );
  }

  public function getQuickCreateItems(PhabricatorUser $viewer) {
    $items = array();

    $item = id(new PHUIListItemView())
      ->setName(pht('Conpherence Thread'))
      ->setIcon('fa-comments')
      ->setWorkflow(true)
      ->setHref($this->getBaseURI().'new/');
    $items[] = $item;

    return $items;
  }

  public function getQuicksandURIPatternBlacklist() {
    return array(
      '/conpherence/.*',
    );
  }

}

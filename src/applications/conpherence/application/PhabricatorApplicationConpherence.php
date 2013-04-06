<?php

/**
 * @group conpherence
 */
final class PhabricatorApplicationConpherence extends PhabricatorApplication {

  public function isBeta() {
    return true;
  }

  public function getBaseURI() {
    return '/conpherence/';
  }

  public function getQuickCreateURI() {
    return $this->getBaseURI().'new/';
  }

  public function getShortDescription() {
    return pht('Messaging');
  }

  public function getIconName() {
    return 'conpherence';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\x8E";
  }

  public function getApplicationGroup() {
    return self::GROUP_COMMUNICATION;
  }

  public function getEventListeners() {
    return array(
      new ConpherencePeopleMenuEventListener(),
      new ConpherenceHovercardEventListener(),
    );
  }

  public function getRoutes() {
    return array(
      '/conpherence/' => array(
        ''                         => 'ConpherenceListController',
        'thread/(?P<id>[1-9]\d*)/' => 'ConpherenceListController',
        '(?P<id>[1-9]\d*)/'        => 'ConpherenceViewController',
        'new/'                     => 'ConpherenceNewController',
        'widget/(?P<id>[1-9]\d*)/' => 'ConpherenceWidgetController',
        'update/(?P<id>[1-9]\d*)/' => 'ConpherenceUpdateController',
      ),
    );
  }

}

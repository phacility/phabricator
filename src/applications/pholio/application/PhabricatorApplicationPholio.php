<?php

/**
 * @group pholio
 */
final class PhabricatorApplicationPholio extends PhabricatorApplication {

  public function getBaseURI() {
    return '/pholio/';
  }

  public function getShortDescription() {
    return pht('Design Review');
  }

  public function getIconName() {
    return 'pholio';
  }

  public function getTitleGlyph() {
    return "\xE2\x9D\xA6";
  }

  public function getFlavorText() {
    return pht('Things before they were cool.');
  }

  public function getApplicationGroup() {
    // TODO: Move to CORE, this just keeps it out of the side menu.
    return self::GROUP_COMMUNICATION;
  }

  public function isBeta() {
    return true;
  }

  public function getRemarkupRules() {
    return array(
      new PholioRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/M(?P<id>[1-9]\d*)(?:/(?P<imageID>\d+)/)?' => 'PholioMockViewController',
      '/pholio/' => array(
        '' => 'PholioMockListController',
        'view/(?P<view>\w+)/'   => 'PholioMockListController',
        'new/'                  => 'PholioMockEditController',
        'edit/(?P<id>\d+)/'     => 'PholioMockEditController',
        'comment/(?P<id>\d+)/'  => 'PholioMockCommentController',
        'inline/' => array(
          '(?P<id>\d+)/' => 'PholioInlineController',
          'save/' => 'PholioInlineSaveController',
          'delete/(?P<id>\d+)/' => 'PholioInlineDeleteController',
          'view/(?P<id>\d+)/' => 'PholioInlineViewController',
          'edit/(?P<id>\d+)/' => 'PholioInlineEditController'
        ),
      ),
    );
  }

}

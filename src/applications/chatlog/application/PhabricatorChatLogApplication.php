<?php

final class PhabricatorChatLogApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/chatlog/';
  }

  public function getName() {
    return pht('ChatLog');
  }

  public function getShortDescription() {
    return pht('(Deprecated)');
  }

  public function getIcon() {
    return 'fa-coffee';
  }

  public function isPrototype() {
    return true;
  }

  public function getTitleGlyph() {
    return "\xE0\xBC\x84";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

 public function getRoutes() {
    return array(
      '/chatlog/' => array(
       '' => 'PhabricatorChatLogChannelListController',
       'channel/(?P<channelID>[^/]+)/'
          => 'PhabricatorChatLogChannelLogController',
       ),
    );
  }

}

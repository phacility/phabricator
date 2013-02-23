<?php

final class PhabricatorApplicationChatLog extends PhabricatorApplication {

  public function getBaseURI() {
    return '/chatlog/';
  }

  public function getShortDescription() {
    return pht('Chat Log');
  }

  public function getIconName() {
    return 'chatlog';
  }

  public function isBeta() {
    return true;
  }

  public function getTitleGlyph() {
    return "\xE0\xBC\x84";
  }

  public function getApplicationGroup() {
    return self::GROUP_COMMUNICATION;
  }

 public function getRoutes() {
    return array(
      '/chatlog/' => array(
       ''         => 'PhabricatorChatLogChannelListController',
       'channel/(?P<channelID>[^/]+)/' =>
          'PhabricatorChatLogChannelLogController',
       ),

    );
  }

}


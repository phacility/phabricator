<?php

final class ConpherenceRoomSettings extends ConpherenceConstants {

  const SOUND_RECEIVE = 'receive';
  const SOUND_MENTION = 'mention';

  const DEFAULT_RECEIVE_SOUND = 'tap';
  const DEFAULT_MENTION_SOUND = 'alert';
  const DEFAULT_NO_SOUND = 'none';

  public static function getSoundMap() {
    return array(
      'none' => array(
        'name' => pht('No Sound'),
        'rsrc' => '',
      ),
      'alert' => array(
        'name' => pht('Alert'),
        'rsrc' => celerity_get_resource_uri('/rsrc/audio/basic/alert.mp3'),
      ),
      'bing' => array(
        'name' => pht('Bing'),
        'rsrc' => celerity_get_resource_uri('/rsrc/audio/basic/bing.mp3'),
      ),
      'pock' => array(
        'name' => pht('Pock'),
        'rsrc' => celerity_get_resource_uri('/rsrc/audio/basic/pock.mp3'),
      ),
      'tap' => array(
        'name' => pht('Tap'),
        'rsrc' => celerity_get_resource_uri('/rsrc/audio/basic/tap.mp3'),
      ),
      'ting' => array(
        'name' => pht('Ting'),
        'rsrc' => celerity_get_resource_uri('/rsrc/audio/basic/ting.mp3'),
      ),
    );
  }

  public static function getDropdownSoundMap() {
    $map = self::getSoundMap();
    return ipull($map, 'name');
  }


}

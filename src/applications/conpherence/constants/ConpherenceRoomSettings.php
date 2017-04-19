<?php

final class ConpherenceRoomSettings extends ConpherenceConstants {

  const SOUND_RECEIVE = 'receive';
  const SOUND_MENTION = 'mention';

  const DEFAULT_RECEIVE_SOUND = 'tap';
  const DEFAULT_MENTION_SOUND = 'tap'; // Upload a new sound
  const DEFAULT_NO_SOUND = 'none';

  public static function getSoundMap() {
    return array(
      'none' => array(
        'name' => pht('No Sound'),
        'rsrc' => '',
      ),
      'tap' => array(
        'name' => pht('Tap'),
        'rsrc' => celerity_get_resource_uri('/rsrc/audio/basic/tap.mp3'),
      ),
    );
  }

  public static function getDropdownSoundMap() {
    $map = self::getSoundMap();
    return ipull($map, 'name');
  }


}

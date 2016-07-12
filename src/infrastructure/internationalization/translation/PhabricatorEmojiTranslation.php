<?php

final class PhabricatorEmojiTranslation
  extends PhutilTranslation {

  public function getLocaleCode() {
    return 'en_X*';
  }

  protected function getTranslations() {
    return array(
      'Emoji (Internet)' => "\xF0\x9F\x92\xAC (\xF0\x9F\x8C\x8D)",
    );
  }
}

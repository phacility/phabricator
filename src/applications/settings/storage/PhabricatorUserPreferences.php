<?php

final class PhabricatorUserPreferences extends PhabricatorUserDAO {

  const PREFERENCE_MONOSPACED        = 'monospaced';
  const PREFERENCE_EDITOR            = 'editor';
  const PREFERENCE_TITLES            = 'titles';

  const PREFERENCE_RE_PREFIX         = 're-prefix';
  const PREFERENCE_NO_SELF_MAIL      = 'self-mail';
  const PREFERENCE_MAILTAGS          = 'mailtags';
  const PREFERENCE_VARY_SUBJECT      = 'vary-subject';

  const PREFERENCE_SEARCHBAR_JUMP    = 'searchbar-jump';
  const PREFERENCE_SEARCH_SHORTCUT   = 'search-shortcut';

  const PREFERENCE_DIFFUSION_VIEW    = 'diffusion-view';
  const PREFERENCE_DIFFUSION_SYMBOLS = 'diffusion-symbols';

  const PREFERENCE_NAV_WIDTH         = 'nav-width';

  protected $userPHID;
  protected $preferences = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'preferences' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function getPreference($key, $default = null) {
    return idx($this->preferences, $key, $default);
  }

  public function setPreference($key, $value) {
    $this->preferences[$key] = $value;
    return $this;
  }

  public function unsetPreference($key) {
    unset($this->preferences[$key]);
    return $this;
  }

}

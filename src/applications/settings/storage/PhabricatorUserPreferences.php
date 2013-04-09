<?php

final class PhabricatorUserPreferences extends PhabricatorUserDAO {

  const PREFERENCE_MONOSPACED           = 'monospaced';
  const PREFERENCE_DARK_CONSOLE         = 'dark_console';
  const PREFERENCE_EDITOR               = 'editor';
  const PREFERENCE_MULTIEDIT            = 'multiedit';
  const PREFERENCE_TITLES               = 'titles';
  const PREFERENCE_MONOSPACED_TEXTAREAS = 'monospaced-textareas';

  const PREFERENCE_RE_PREFIX            = 're-prefix';
  const PREFERENCE_NO_SELF_MAIL         = 'self-mail';
  const PREFERENCE_MAILTAGS             = 'mailtags';
  const PREFERENCE_VARY_SUBJECT         = 'vary-subject';

  const PREFERENCE_SEARCHBAR_JUMP       = 'searchbar-jump';
  const PREFERENCE_SEARCH_SHORTCUT      = 'search-shortcut';

  const PREFERENCE_DIFFUSION_VIEW       = 'diffusion-view';

  const PREFERENCE_NAV_COLLAPSED        = 'nav-collapsed';
  const PREFERENCE_NAV_WIDTH            = 'nav-width';
  const PREFERENCE_APP_TILES            = 'app-tiles';

  const PREFERENCE_DIFF_FILETREE        = 'diff-filetree';

  const PREFERENCE_CONPH_NOTIFICATIONS  = 'conph-notifications';

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

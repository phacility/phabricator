<?php

final class PhabricatorUserPreferences extends PhabricatorUserDAO {

  const PREFERENCE_MONOSPACED           = 'monospaced';
  const PREFERENCE_DARK_CONSOLE         = 'dark_console';
  const PREFERENCE_EDITOR               = 'editor';
  const PREFERENCE_MULTIEDIT            = 'multiedit';
  const PREFERENCE_TITLES               = 'titles';
  const PREFERENCE_MONOSPACED_TEXTAREAS = 'monospaced-textareas';
  const PREFERENCE_TIME_FORMAT          = 'time-format';
  const PREFERENCE_WEEK_START_DAY       = 'week-start-day';

  const PREFERENCE_RE_PREFIX            = 're-prefix';
  const PREFERENCE_NO_SELF_MAIL         = 'self-mail';
  const PREFERENCE_NO_MAIL              = 'no-mail';
  const PREFERENCE_MAILTAGS             = 'mailtags';
  const PREFERENCE_VARY_SUBJECT         = 'vary-subject';
  const PREFERENCE_HTML_EMAILS          = 'html-emails';

  const PREFERENCE_SEARCHBAR_JUMP       = 'searchbar-jump';
  const PREFERENCE_SEARCH_SHORTCUT      = 'search-shortcut';
  const PREFERENCE_SEARCH_SCOPE         = 'search-scope';

  const PREFERENCE_DIFFUSION_BLAME      = 'diffusion-blame';
  const PREFERENCE_DIFFUSION_COLOR      = 'diffusion-color';

  const PREFERENCE_NAV_COLLAPSED        = 'nav-collapsed';
  const PREFERENCE_NAV_WIDTH            = 'nav-width';
  const PREFERENCE_APP_TILES            = 'app-tiles';
  const PREFERENCE_APP_PINNED           = 'app-pinned';

  const PREFERENCE_DIFF_UNIFIED         = 'diff-unified';
  const PREFERENCE_DIFF_FILETREE        = 'diff-filetree';
  const PREFERENCE_DIFF_GHOSTS          = 'diff-ghosts';

  const PREFERENCE_CONPH_NOTIFICATIONS = 'conph-notifications';
  const PREFERENCE_CONPHERENCE_COLUMN  = 'conpherence-column';

  // These are in an unusual order for historic reasons.
  const MAILTAG_PREFERENCE_NOTIFY       = 0;
  const MAILTAG_PREFERENCE_EMAIL        = 1;
  const MAILTAG_PREFERENCE_IGNORE       = 2;

  protected $userPHID;
  protected $preferences = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'preferences' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_KEY_SCHEMA => array(
        'userPHID' => array(
          'columns' => array('userPHID'),
          'unique' => true,
        ),
      ),
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

  public function getPinnedApplications(array $apps, PhabricatorUser $viewer) {
    $pref_pinned = self::PREFERENCE_APP_PINNED;
    $pinned = $this->getPreference($pref_pinned);

    if ($pinned) {
      return $pinned;
    }

    $pref_tiles = self::PREFERENCE_APP_TILES;
    $tiles = $this->getPreference($pref_tiles, array());
    $full_tile = 'full';

    $large = array();
    foreach ($apps as $app) {
      $show = $app->isPinnedByDefault($viewer);

      // TODO: This is legacy stuff, clean it up eventually. This approximately
      // retains the old "tiles" preference.
      if (isset($tiles[get_class($app)])) {
        $show = ($tiles[get_class($app)] == $full_tile);
      }

      if ($show) {
        $large[] = get_class($app);
      }
    }

    return $large;
  }

  public static function filterMonospacedCSSRule($monospaced) {
    // Prevent the user from doing dangerous things.
    return preg_replace('/[^a-z0-9 ,".]+/i', '', $monospaced);
  }

}

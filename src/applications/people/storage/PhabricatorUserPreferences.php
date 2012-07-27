<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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

<?php

/*
 * Copyright 2011 Facebook, Inc.
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

/**
 * @group markup
 */
class PhabricatorRemarkupRuleMention
  extends PhutilRemarkupRule {

  private $actualUsers;

  public function apply($text) {

    // NOTE: Negative lookahead for period prevents us from picking up email
    // addresses, while allowing constructs like "@tomo, lol". The negative
    // lookbehind for a word character prevents us from matching "mail@lists"
    // while allowing "@tomo/@mroch".
    $regexp = '/(?<!\w)@([a-zA-Z0-9]+)\b(?![.])/';

    $matches = null;
    $ok = preg_match_all($regexp, $text, $matches);
    if (!$ok) {
      // No mentions in this text.
      return $text;
    }

    $usernames = $matches[1];

    // TODO: This is a little sketchy perf-wise. Once APC comes up, it is an
    // ideal candidate to back with an APC cache.
    $user_table = new PhabricatorUser();
    $real_user_names = queryfx_all(
      $user_table->establishConnection('r'),
      'SELECT username, phid, realName FROM %T WHERE username IN (%Ls)',
      $user_table->getTableName(),
      $usernames);

    $engine = $this->getEngine();
    $metadata_key = 'phabricator.mentioned-user-phids';
    $mentioned = $engine->getTextMetadata($metadata_key, array());

    foreach ($real_user_names as $row) {
      $this->actualUsers[strtolower($row['username'])] = $row;
      $mentioned[$row['phid']] = $row['phid'];
    }

    $engine->setTextMetadata($metadata_key, $mentioned);

    return preg_replace_callback(
      $regexp,
      array($this, 'markupMention'),
      $text);
  }

  public function markupMention($matches) {
    $username = strtolower($matches[1]);
    $exists = isset($this->actualUsers[$username]);

    $real = $this->actualUsers[$username]['realName'];

    $class = $exists
      ? 'phabricator-remarkup-mention-exists'
      : 'phabricator-remarkup-mention-unknown';

    if ($exists) {
      $tag = phutil_render_tag(
        'a',
        array(
          'class'   => $class,
          'href'    => '/p/'.$username.'/',
          'target'  => '_blank',
          'title'   => $real,
        ),
        phutil_escape_html('@'.$username));
    } else {
      $tag = phutil_render_tag(
        'span',
        array(
          'class' => $class,
        ),
        phutil_escape_html('@'.$username));
    }

    return $this->getEngine()->storeText($tag);
  }

}

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

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleMention
  extends PhutilRemarkupRule {

  const KEY_RULE_MENTION          = 'rule.mention';
  const KEY_RULE_MENTION_ORIGINAL = 'rule.mention.original';

  const KEY_MENTIONED = 'phabricator.mentioned-user-phids';


  // NOTE: Negative lookahead for period prevents us from picking up email
  // addresses, while allowing constructs like "@tomo, lol". The negative
  // lookbehind for a word character prevents us from matching "mail@lists"
  // while allowing "@tomo/@mroch". The negative lookahead prevents us from
  // matching "@joe.com" while allowing us to match "hey, @joe.".
  const REGEX = '/(?<!\w)@([a-zA-Z0-9]+)\b(?![.]\w)/';

  public function apply($text) {
    return preg_replace_callback(
      self::REGEX,
      array($this, 'markupMention'),
      $text);
  }

  private function markupMention($matches) {
    $engine = $this->getEngine();
    $token = $engine->storeText('');

    // Store the original text exactly so we can preserve casing if it doesn't
    // resolve into a username.
    $original_key = self::KEY_RULE_MENTION_ORIGINAL;
    $original = $engine->getTextMetadata($original_key, array());
    $original[$token] = $matches[1];
    $engine->setTextMetadata($original_key, $original);

    $metadata_key = self::KEY_RULE_MENTION;
    $metadata = $engine->getTextMetadata($metadata_key, array());
    $username = strtolower($matches[1]);
    if (empty($metadata[$username])) {
      $metadata[$username] = array();
    }
    $metadata[$username][] = $token;
    $engine->setTextMetadata($metadata_key, $metadata);

    return $token;
  }

  public function didMarkupText() {
    $engine = $this->getEngine();

    $metadata_key = self::KEY_RULE_MENTION;
    $metadata = $engine->getTextMetadata($metadata_key, array());
    if (empty($metadata)) {
      // No mentions, or we already processed them.
      return;
    }

    $original_key = self::KEY_RULE_MENTION_ORIGINAL;
    $original = $engine->getTextMetadata($original_key, array());

    $usernames = array_keys($metadata);
    $user_table = new PhabricatorUser();
    $real_user_names = queryfx_all(
      $user_table->establishConnection('r'),
      'SELECT username, phid, realName FROM %T WHERE username IN (%Ls)',
      $user_table->getTableName(),
      $usernames);

    $actual_users = array();

    $mentioned_key = self::KEY_MENTIONED;
    $mentioned = $engine->getTextMetadata($mentioned_key, array());
    foreach ($real_user_names as $row) {
      $actual_users[strtolower($row['username'])] = $row;
      $mentioned[$row['phid']] = $row['phid'];
    }

    $engine->setTextMetadata($mentioned_key, $mentioned);

    foreach ($metadata as $username => $tokens) {
      $exists = isset($actual_users[$username]);
      $class = $exists
        ? 'phabricator-remarkup-mention-exists'
        : 'phabricator-remarkup-mention-unknown';

      if ($exists) {
        $tag = phutil_render_tag(
          'a',
          array(
            'class'   => $class,
            'href'    => '/p/'.$actual_users[$username]['username'].'/',
            'target'  => '_blank',
            'title'   => $actual_users[$username]['realName'],
          ),
          phutil_escape_html('@'.$actual_users[$username]['username']));
        foreach ($tokens as $token) {
          $engine->overwriteStoredText($token, $tag);
        }
      } else {
        // NOTE: The structure here is different from the 'exists' branch,
        // because we want to preserve the original text capitalization and it
        // may differ for each token.
        foreach ($tokens as $token) {
          $tag = phutil_render_tag(
            'span',
            array(
              'class' => $class,
            ),
            phutil_escape_html('@'.idx($original, $token, $username)));
          $engine->overwriteStoredText($token, $tag);
        }
      }
    }

    // Don't re-process these mentions.
    $engine->setTextMetadata($metadata_key, array());
  }

}

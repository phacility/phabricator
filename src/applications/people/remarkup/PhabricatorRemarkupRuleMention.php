<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleMention
  extends PhutilRemarkupRule {

  const KEY_RULE_MENTION          = 'rule.mention';
  const KEY_RULE_MENTION_ORIGINAL = 'rule.mention.original';

  const KEY_MENTIONED = 'phabricator.mentioned-user-phids';


  // NOTE: The negative lookbehind prevents matches like "mail@lists", while
  // allowing constructs like "@tomo/@mroch". Since we now allow periods in
  // usernames, we can't resonably distinguish that "@company.com" isn't a
  // username, so we'll incorrectly pick it up, but there's little to be done
  // about that. We forbid terminal periods so that we can correctly capture
  // "@joe" instead of "@joe." in "Hey, @joe.".
  const REGEX = '/(?<!\w)@([a-zA-Z0-9._-]*[a-zA-Z0-9_-])/';

  public function apply($text) {
    return preg_replace_callback(
      self::REGEX,
      array($this, 'markupMention'),
      $text);
  }

  protected function markupMention($matches) {
    $engine = $this->getEngine();

    if ($engine->isTextMode()) {
      return $engine->storeText($matches[0]);
    }

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

    $users = id(new PhabricatorPeopleQuery())
      ->withUsernames($usernames)
      ->execute();

    if ($users) {
      $user_statuses = id(new PhabricatorUserStatus())
        ->loadCurrentStatuses(mpull($users, 'getPHID'));
      $user_statuses = mpull($user_statuses, null, 'getUserPHID');
    } else {
      $user_statuses = array();
    }

    $actual_users = array();

    $mentioned_key = self::KEY_MENTIONED;
    $mentioned = $engine->getTextMetadata($mentioned_key, array());
    foreach ($users as $row) {
      $actual_users[strtolower($row->getUserName())] = $row;
      $mentioned[$row->getPHID()] = $row->getPHID();
    }

    $engine->setTextMetadata($mentioned_key, $mentioned);

    foreach ($metadata as $username => $tokens) {
      $exists = isset($actual_users[$username]);

      if ($exists) {
        $user = $actual_users[$username];
        Javelin::initBehavior('phabricator-hovercards');

        $tag = id(new PhabricatorTagView())
          ->setType(PhabricatorTagView::TYPE_PERSON)
          ->setPHID($user->getPHID())
          ->setName('@'.$user->getUserName())
          ->setHref('/p/'.$user->getUserName().'/');

        if ($user->getIsDisabled()) {
          $tag->setDotColor(PhabricatorTagView::COLOR_GREY);
        } else {
          $status = idx($user_statuses, $user->getPHID());
          if ($status) {
            $status = $status->getStatus();
            if ($status == PhabricatorUserStatus::STATUS_AWAY) {
              $tag->setDotColor(PhabricatorTagView::COLOR_RED);
            } else if ($status == PhabricatorUserStatus::STATUS_AWAY) {
              $tag->setDotColor(PhabricatorTagView::COLOR_ORANGE);
            }
          }
        }

        foreach ($tokens as $token) {
          $engine->overwriteStoredText($token, $tag);
        }
      } else {
        // NOTE: The structure here is different from the 'exists' branch,
        // because we want to preserve the original text capitalization and it
        // may differ for each token.
        foreach ($tokens as $token) {
          $tag = phutil_tag(
            'span',
            array(
              'class' => 'phabricator-remarkup-mention-unknown',
            ),
            '@'.idx($original, $token, $username));
          $engine->overwriteStoredText($token, $tag);
        }
      }
    }

    // Don't re-process these mentions.
    $engine->setTextMetadata($metadata_key, array());
  }

}

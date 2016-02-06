<?php

final class PhabricatorMentionRemarkupRule extends PhutilRemarkupRule {

  const KEY_RULE_MENTION          = 'rule.mention';
  const KEY_RULE_MENTION_ORIGINAL = 'rule.mention.original';

  const KEY_MENTIONED = 'phabricator.mentioned-user-phids';


  // NOTE: The negative lookbehind prevents matches like "mail@lists", while
  // allowing constructs like "@tomo/@mroch". Since we now allow periods in
  // usernames, we can't resonably distinguish that "@company.com" isn't a
  // username, so we'll incorrectly pick it up, but there's little to be done
  // about that. We forbid terminal periods so that we can correctly capture
  // "@joe" instead of "@joe." in "Hey, @joe.".
  //
  // We disallow "@@joe" because it creates a false positive in the common
  // construction "l@@k", made popular by eBay.
  const REGEX = '/(?<!\w|@)@([a-zA-Z0-9._-]*[a-zA-Z0-9_-])/';

  public function apply($text) {
    return preg_replace_callback(
      self::REGEX,
      array($this, 'markupMention'),
      $text);
  }

  protected function markupMention(array $matches) {
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
      ->setViewer($this->getEngine()->getConfig('viewer'))
      ->withUsernames($usernames)
      ->needAvailability(true)
      ->execute();

    $actual_users = array();

    $mentioned_key = self::KEY_MENTIONED;
    $mentioned = $engine->getTextMetadata($mentioned_key, array());
    foreach ($users as $row) {
      $actual_users[strtolower($row->getUserName())] = $row;
      $mentioned[$row->getPHID()] = $row->getPHID();
    }

    $engine->setTextMetadata($mentioned_key, $mentioned);
    $context_object = $engine->getConfig('contextObject');

    foreach ($metadata as $username => $tokens) {
      $exists = isset($actual_users[$username]);
      $user_has_no_permission = false;

      if ($exists) {
        $user = $actual_users[$username];
        Javelin::initBehavior('phui-hovercards');

        // Check if the user has view access to the object she was mentioned in
        if ($context_object
          && $context_object instanceof PhabricatorPolicyInterface) {
          if (!PhabricatorPolicyFilter::hasCapability(
            $user,
            $context_object,
            PhabricatorPolicyCapability::CAN_VIEW)) {
            // User mentioned has no permission to this object
            $user_has_no_permission = true;
          }
        }

        $user_href = '/p/'.$user->getUserName().'/';

        if ($engine->isHTMLMailMode()) {
          $user_href = PhabricatorEnv::getProductionURI($user_href);

          if ($user_has_no_permission) {
            $colors = '
              border-color: #92969D;
              color: #92969D;
              background-color: #F7F7F7;';
          } else {
            $colors = '
              border-color: #f1f7ff;
              color: #19558d;
              background-color: #f1f7ff;';
          }

          $tag = phutil_tag(
            'a',
            array(
              'href' => $user_href,
              'style' => $colors.'
                border: 1px solid transparent;
                border-radius: 3px;
                font-weight: bold;
                padding: 0 4px;',
            ),
            '@'.$user->getUserName());
        } else {
          $tag = id(new PHUITagView())
            ->setType(PHUITagView::TYPE_PERSON)
            ->setPHID($user->getPHID())
            ->setName('@'.$user->getUserName())
            ->setHref($user_href);

          if ($user_has_no_permission) {
            $tag->addClass('phabricator-remarkup-mention-nopermission');
          }

          if (!$user->isUserActivated()) {
            $tag->setDotColor(PHUITagView::COLOR_GREY);
          } else {
            if ($user->getAwayUntil()) {
              $tag->setDotColor(PHUITagView::COLOR_RED);
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

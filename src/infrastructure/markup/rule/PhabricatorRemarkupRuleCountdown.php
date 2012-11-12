<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleCountdown extends PhutilRemarkupRule {

  const KEY_RULE_COUNTDOWN = 'rule.countdown';

  public function apply($text) {
    return preg_replace_callback(
      "@\B{C(\d+)}\B@",
      array($this, 'markupCountdown'),
      $text);
  }

  private function markupCountdown($matches) {
    $countdown = id(new PhabricatorTimer())->load($matches[1]);
    if (!$countdown) {
      return $matches[0];
    }
    $id = celerity_generate_unique_node_id();

    $engine = $this->getEngine();
    $token = $engine->storeText('');

    $metadata_key = self::KEY_RULE_COUNTDOWN;
    $metadata = $engine->getTextMetadata($metadata_key, array());
    $metadata[$id] = array($countdown->getDatepoint(), $token);
    $engine->setTextMetadata($metadata_key, $metadata);

    return $token;
  }

  public function didMarkupText() {
    $engine = $this->getEngine();

    $metadata_key = self::KEY_RULE_COUNTDOWN;
    $metadata = $engine->getTextMetadata($metadata_key, array());

    if (!$metadata) {
      return;
    }

    require_celerity_resource('javelin-behavior-countdown-timer');

    foreach ($metadata as $id => $info) {
      list($time, $token) = $info;
      $count = phutil_render_tag(
        'span',
        array(
          'id' => $id,
        ),
        javelin_render_tag('span',
          array('sigil' => 'phabricator-timer-days'), '').'d'.
        javelin_render_tag('span',
          array('sigil' => 'phabricator-timer-hours'), '').'h'.
        javelin_render_tag('span',
          array('sigil' => 'phabricator-timer-minutes'), '').'m'.
        javelin_render_tag('span',
          array('sigil' => 'phabricator-timer-seconds'), '').'s');
      Javelin::initBehavior('countdown-timer', array(
        'timestamp' => $time,
        'container' => $id,
      ));
      $engine->overwriteStoredText($token, $count);
    }

    $engine->setTextMetadata($metadata_key, array());
  }

}

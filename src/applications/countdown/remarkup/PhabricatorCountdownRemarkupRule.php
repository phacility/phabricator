<?php

/**
 * @group markup
 */
final class PhabricatorCountdownRemarkupRule extends PhutilRemarkupRule {

  const KEY_RULE_COUNTDOWN = 'rule.countdown';

  public function apply($text) {
    return preg_replace_callback(
      "@\B{C(\d+)}\B@",
      array($this, 'markupCountdown'),
      $text);
  }

  protected function markupCountdown($matches) {
    $countdown = id(new PhabricatorTimer())->load($matches[1]);
    if (!$countdown) {
      return $matches[0];
    }

    $engine = $this->getEngine();

    if ($engine->isTextMode()) {
      $date = $countdown->getDatepoint();
      $viewer = $engine->getConfig('viewer');
      if ($viewer) {
        $date = phabricator_datetime($date, $viewer);
      }
      return $engine->storeText($date);
    }

    $id = celerity_generate_unique_node_id();
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
      $prefix = 'phabricator-timer-';
      $count = phutil_tag(
        'span',
        array(
          'id' => $id,
        ),
        array(
          javelin_tag('span', array('sigil' => $prefix.'days'), ''), 'd',
          javelin_tag('span', array('sigil' => $prefix.'hours'), ''), 'h',
          javelin_tag('span', array('sigil' => $prefix.'minutes'), ''), 'm',
          javelin_tag('span', array('sigil' => $prefix.'seconds'), ''), 's',
        ));
      Javelin::initBehavior('countdown-timer', array(
        'timestamp' => $time,
        'container' => $id,
      ));
      $engine->overwriteStoredText($token, $count);
    }

    $engine->setTextMetadata($metadata_key, array());
  }

}

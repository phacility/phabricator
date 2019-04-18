<?php

final class PhabricatorProjectTriggerPlaySoundRule
  extends PhabricatorProjectTriggerRule {

  const TRIGGERTYPE = 'sound';

  public function getSelectControlName() {
    return pht('Play sound');
  }

  protected function assertValidRuleRecordFormat($value) {
    if (!is_string($value)) {
      throw new Exception(
        pht(
          'Status rule value should be a string, but is not (value is "%s").',
          phutil_describe_type($value)));
    }
  }

  protected function assertValidRuleRecordValue($value) {
    $map = self::getSoundMap();
    if (!isset($map[$value])) {
      throw new Exception(
        pht(
          'Sound ("%s") is not a valid sound.',
          $value));
    }
  }

  protected function newDropTransactions($object, $value) {
    return array();
  }

  protected function newDropEffects($value) {
    $sound_icon = 'fa-volume-up';
    $sound_color = 'blue';
    $sound_name = self::getSoundName($value);

    $content = pht(
      'Play sound %s.',
      phutil_tag('strong', array(), $sound_name));

    return array(
      $this->newEffect()
        ->setIcon($sound_icon)
        ->setColor($sound_color)
        ->setContent($content),
    );
  }

  protected function getDefaultValue() {
    return head_key(self::getSoundMap());
  }

  protected function getPHUIXControlType() {
    return 'select';
  }

  protected function getPHUIXControlSpecification() {
    $map = self::getSoundMap();
    $map = ipull($map, 'name');

    return array(
      'options' => $map,
      'order' => array_keys($map),
    );
  }

  public function getRuleViewLabel() {
    return pht('Play Sound');
  }

  public function getRuleViewDescription($value) {
    $sound_name = self::getSoundName($value);

    return pht(
      'Play sound %s.',
      phutil_tag('strong', array(), $sound_name));
  }

  public function getRuleViewIcon($value) {
    $sound_icon = 'fa-volume-up';
    $sound_color = 'blue';

    return id(new PHUIIconView())
      ->setIcon($sound_icon, $sound_color);
  }

  private static function getSoundName($value) {
    $map = self::getSoundMap();
    $spec = idx($map, $value, array());
    return idx($spec, 'name', $value);
  }

  private static function getSoundMap() {
    return array(
      'bing' => array(
        'name' => pht('Bing'),
        'uri' => celerity_get_resource_uri('/rsrc/audio/basic/bing.mp3'),
      ),
      'glass' => array(
        'name' => pht('Glass'),
        'uri' => celerity_get_resource_uri('/rsrc/audio/basic/ting.mp3'),
      ),
    );
  }

  public function getSoundEffects() {
    $value = $this->getValue();

    $map = self::getSoundMap();
    $spec = idx($map, $value, array());

    $uris = array();
    if (isset($spec['uri'])) {
      $uris[] = $spec['uri'];
    }

    return $uris;
  }

}

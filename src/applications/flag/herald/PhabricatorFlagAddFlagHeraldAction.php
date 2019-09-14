<?php

final class PhabricatorFlagAddFlagHeraldAction
  extends PhabricatorFlagHeraldAction {

  const ACTIONCONST = 'flag';

  const DO_FLAG = 'do.flag';
  const DO_IGNORE = 'do.flagged';

  public function getHeraldActionName() {
    return pht('Mark with flag');
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $phid = $this->getAdapter()->getPHID();
    $rule = $effect->getRule();
    $author = $rule->getAuthor();

    $flag = PhabricatorFlagQuery::loadUserFlag($author, $phid);
    if ($flag) {
      $this->logEffect(self::DO_IGNORE, $flag->getColor());
      return;
    }

    $flag = id(new PhabricatorFlag())
      ->setOwnerPHID($author->getPHID())
      ->setType(phid_get_type($phid))
      ->setObjectPHID($phid)
      ->setReasonPHID($rule->getPHID())
      ->setColor($effect->getTarget())
      ->setNote('')
      ->save();

    $this->logEffect(self::DO_FLAG, $flag->getColor());
  }

  public function getHeraldActionValueType() {
    return id(new HeraldSelectFieldValue())
      ->setKey('flag.color')
      ->setOptions(PhabricatorFlagColor::getColorNameMap())
      ->setDefault(PhabricatorFlagColor::COLOR_BLUE);
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_IGNORE => array(
        'icon' => 'fa-times',
        'color' => 'grey',
        'name' => pht('Already Marked'),
      ),
      self::DO_FLAG => array(
        'icon' => 'fa-flag',
        'name' => pht('Flagged'),
      ),
    );
  }

  public function renderActionDescription($value) {
    $color = PhabricatorFlagColor::getColorName($value);
    return pht('Mark with %s flag.', $color);
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_IGNORE:
        return pht(
          'Already marked with %s flag.',
          PhabricatorFlagColor::getColorName($data));
      case self::DO_FLAG:
        return pht(
          'Marked with "%s" flag.',
          PhabricatorFlagColor::getColorName($data));
    }
  }

}

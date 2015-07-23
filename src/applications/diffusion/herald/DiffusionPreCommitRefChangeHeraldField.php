<?php

final class DiffusionPreCommitRefChangeHeraldField
  extends DiffusionPreCommitRefHeraldField {

  const FIELDCONST = 'diffusion.pre.ref.change';

  public function getHeraldFieldName() {
    return pht('Ref change type');
  }

  public function getHeraldFieldValue($object) {
    return $object->getChangeFlags();
  }

  public function getHeraldFieldConditions() {
    return array(
      HeraldAdapter::CONDITION_HAS_BIT,
      HeraldAdapter::CONDITION_NOT_BIT,
    );
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldPreCommitRefAdapter::VALUE_REF_CHANGE;
  }

  public function renderConditionValue(
    PhabricatorUser $viewer,
    $value) {

    $change_map =
      PhabricatorRepositoryPushLog::getHeraldChangeFlagConditionOptions();
    foreach ($value as $index => $val) {
      $name = idx($change_map, $val);
      if ($name) {
        $value[$index] = $name;
      }
    }

    return phutil_implode_html(', ', $value);
  }

}

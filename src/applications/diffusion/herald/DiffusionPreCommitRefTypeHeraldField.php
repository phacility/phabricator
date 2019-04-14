<?php

final class DiffusionPreCommitRefTypeHeraldField
  extends DiffusionPreCommitRefHeraldField {

  const FIELDCONST = 'diffusion.pre.ref.type';

  public function getHeraldFieldName() {
    return pht('Ref type');
  }

  public function getHeraldFieldValue($object) {
    return $object->getRefType();
  }

  public function getHeraldFieldConditions() {
    return array(
      HeraldAdapter::CONDITION_IS,
      HeraldAdapter::CONDITION_IS_NOT,
    );
  }

  public function getHeraldFieldValueType($condition) {
    $types = array(
      PhabricatorRepositoryPushLog::REFTYPE_BRANCH => pht('branch (git/hg)'),
      PhabricatorRepositoryPushLog::REFTYPE_TAG => pht('tag (git)'),
      PhabricatorRepositoryPushLog::REFTYPE_REF => pht('ref (git)'),
      PhabricatorRepositoryPushLog::REFTYPE_BOOKMARK => pht('bookmark (hg)'),
    );

    return id(new HeraldSelectFieldValue())
      ->setKey(self::FIELDCONST)
      ->setOptions($types)
      ->setDefault(PhabricatorRepositoryPushLog::REFTYPE_BRANCH);
  }

}

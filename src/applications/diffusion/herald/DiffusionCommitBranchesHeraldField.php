<?php

final class DiffusionCommitBranchesHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.branches';

  public function getHeraldFieldName() {
    return pht('Branches');
  }

  public function getHeraldFieldValue($object) {
    $commit = $object;
    $repository = $object->getRepository();

    $params = array(
      'repository' => $repository->getPHID(),
      'contains' => $commit->getCommitIdentifier(),
    );

    $result = id(new ConduitCall('diffusion.branchquery', $params))
      ->setUser(PhabricatorUser::getOmnipotentUser())
      ->execute();

    $refs = DiffusionRepositoryRef::loadAllFromDictionaries($result);

    return mpull($refs, 'getShortName');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT_LIST;
  }

}

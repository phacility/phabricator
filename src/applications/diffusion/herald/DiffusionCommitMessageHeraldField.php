<?php

final class DiffusionCommitMessageHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.message';

  public function getHeraldFieldName() {
    return pht('Commit message');
  }

  public function getHeraldFieldValue($object) {
    return $object->getCommitData()->getCommitMessage();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}

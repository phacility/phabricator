<?php

final class DiffusionPreCommitContentMessageHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.message';

  public function getHeraldFieldName() {
    return pht('Commit message');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getCommitRef()->getMessage();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}

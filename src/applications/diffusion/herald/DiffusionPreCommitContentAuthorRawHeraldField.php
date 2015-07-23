<?php

final class DiffusionPreCommitContentAuthorRawHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.commit.author.raw';

  public function getHeraldFieldName() {
    return pht('Raw Author');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getAuthorRaw();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}

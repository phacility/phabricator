<?php

final class PhabricatorMailEmailSubjectHeraldField
  extends PhabricatorMailEmailHeraldField {

  const FIELDCONST = 'mail.message.subject';

  public function getHeraldFieldName() {
    return pht('Subject');
  }

  public function getHeraldFieldValue($object) {
    return $object->getSubject();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }

}

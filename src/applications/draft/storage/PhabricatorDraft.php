<?php

final class PhabricatorDraft extends PhabricatorDraftDAO {

  protected $authorPHID;
  protected $draftKey;
  protected $draft;
  protected $metadata = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function replaceOrDelete() {
    if ($this->draft == '' && !array_filter($this->metadata)) {
      queryfx(
        $this->establishConnection('w'),
        'DELETE FROM %T WHERE authorPHID = %s AND draftKey = %s',
        $this->getTableName(),
        $this->authorPHID,
        $this->draftKey);
      return $this;
    }
    return parent::replace();
  }

}

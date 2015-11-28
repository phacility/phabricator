<?php

final class PhabricatorEditEngineConfigurationTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'editengine.config.name';
  const TYPE_PREAMBLE = 'editengine.config.preamble';
  const TYPE_ORDER = 'editengine.config.order';
  const TYPE_DEFAULT = 'editengine.config.default';
  const TYPE_LOCKS = 'editengine.config.locks';

  public function getApplicationName() {
    return 'search';
  }

  public function getApplicationTransactionType() {
    return PhabricatorEditEngineConfigurationPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_NAME:
        if (strlen($old)) {
          return pht(
            '%s renamed this form from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        } else {
          return pht(
            '%s named this form "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        }
      case self::TYPE_PREAMBLE:
        return pht(
          '%s updated the preamble for this form.',
            $this->renderHandleLink($author_phid));
      case self::TYPE_ORDER:
        return pht(
          '%s reordered the fields in this form.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_DEFAULT:
        $key = $this->getMetadataValue('field.key');
        return pht(
          '%s changed the default value for field "%s".',
          $this->renderHandleLink($author_phid),
          $key);
      case self::TYPE_LOCKS:
        return pht(
          '%s changed locked and hidden fields.',
          $this->renderHandleLink($author_phid));
    }

    return parent::getTitle();
  }

}

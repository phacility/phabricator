<?php

final class PhabricatorEditEngineConfigurationTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'editengine.config.name';
  const TYPE_PREAMBLE = 'editengine.config.preamble';
  const TYPE_ORDER = 'editengine.config.order';
  const TYPE_DEFAULT = 'editengine.config.default';
  const TYPE_LOCKS = 'editengine.config.locks';
  const TYPE_DEFAULTCREATE = 'editengine.config.default.create';
  const TYPE_ISEDIT = 'editengine.config.isedit';
  const TYPE_DISABLE = 'editengine.config.disable';
  const TYPE_CREATEORDER = 'editengine.order.create';
  const TYPE_EDITORDER = 'editengine.order.edit';
  const TYPE_SUBTYPE = 'editengine.config.subtype';

  public function getApplicationName() {
    return 'search';
  }

  public function getApplicationTransactionType() {
    return PhabricatorEditEngineConfigurationPHIDType::TYPECONST;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case PhabricatorTransactions::TYPE_CREATE:
        return pht(
          '%s created this form configuration.',
          $this->renderHandleLink($author_phid));
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

        $object = $this->getObject();
        $engine = $object->getEngine();
        $fields = $engine->getFieldsForConfig($object);

        $field = idx($fields, $key);
        if (!$field) {
          return pht(
            '%s changed the default value for field "%s".',
            $this->renderHandleLink($author_phid),
            $key);
        }

        return pht(
          '%s changed the default value for field "%s".',
          $this->renderHandleLink($author_phid),
          $field->getLabel());
      case self::TYPE_LOCKS:
        return pht(
          '%s changed locked and hidden fields.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_DEFAULTCREATE:
        if ($new) {
          return pht(
            '%s added this form to the "Create" menu.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s removed this form from the "Create" menu.',
            $this->renderHandleLink($author_phid));
        }
      case self::TYPE_ISEDIT:
        if ($new) {
          return pht(
            '%s marked this form as an edit form.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s unmarked this form as an edit form.',
            $this->renderHandleLink($author_phid));
        }
      case self::TYPE_DISABLE:
        if ($new) {
          return pht(
            '%s disabled this form.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s enabled this form.',
            $this->renderHandleLink($author_phid));
        }
      case self::TYPE_SUBTYPE:
        return pht(
          '%s changed the subtype of this form from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
    }

    return parent::getTitle();
  }

  public function getColor() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case PhabricatorTransactions::TYPE_CREATE:
        return 'green';
      case self::TYPE_DISABLE:
        if ($new) {
          return 'indigo';
        } else {
          return 'green';
        }
    }

    return parent::getColor();
  }

  public function getIcon() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case PhabricatorTransactions::TYPE_CREATE:
        return 'fa-plus';
      case self::TYPE_DISABLE:
        if ($new) {
          return 'fa-ban';
        } else {
          return 'fa-check';
        }
    }

    return parent::getIcon();
  }

  protected function newRemarkupChanges() {
    $changes = array();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_PREAMBLE:
        $changes[] = $this->newRemarkupChange()
          ->setOldValue($this->getOldValue())
          ->setNewValue($this->getNewValue());
        break;
    }

    return $changes;
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DEFAULT:
        return true;
    }

    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    switch ($this->getTransactionType()) {
      case self::TYPE_DEFAULT:
        $old_value = $this->getOldValue();
        $new_value = $this->getNewValue();

        $old_value = $this->renderDefaultValueAsFallbackText($old_value);
        $new_value = $this->renderDefaultValueAsFallbackText($new_value);

        return $this->renderTextCorpusChangeDetails(
          $viewer,
          $old_value,
          $new_value);
    }

    return parent::renderChangeDetails($viewer);
  }

  private function renderDefaultValueAsFallbackText($default_value) {
    // See T13319. When rendering an "alice changed the default value for
    // field X." story on custom forms, we may fall back to a generic
    // rendering. Try to present the value change in a comprehensible way
    // even if it isn't especially human readable (for example, it may
    // contain PHIDs or other internal identifiers).

    if (is_scalar($default_value) || is_null($default_value)) {
      return $default_value;
    }

    if (phutil_is_natural_list($default_value)) {
      return id(new PhutilJSON())->encodeAsList($default_value);
    }

    return id(new PhutilJSON())->encodeAsObject($default_value);
  }

}

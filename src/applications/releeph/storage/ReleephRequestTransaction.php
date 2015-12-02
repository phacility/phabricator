<?php

final class ReleephRequestTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_REQUEST          = 'releeph:request';
  const TYPE_USER_INTENT      = 'releeph:user_intent';
  const TYPE_EDIT_FIELD       = 'releeph:edit_field';
  const TYPE_PICK_STATUS      = 'releeph:pick_status';
  const TYPE_COMMIT           = 'releeph:commit';
  const TYPE_DISCOVERY        = 'releeph:discovery';
  const TYPE_MANUAL_IN_BRANCH = 'releeph:manual';

  public function getApplicationName() {
    return 'releeph';
  }

  public function getApplicationTransactionType() {
    return ReleephRequestPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new ReleephRequestTransactionComment();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      default;
        break;
    }
    return parent::hasChangeDetails();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();
    $phids[] = $this->getObjectPHID();

    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_REQUEST:
      case self::TYPE_DISCOVERY:
        $phids[] = $new;
        break;

      case self::TYPE_EDIT_FIELD:
        self::searchForPHIDs($this->getOldValue(), $phids);
        self::searchForPHIDs($this->getNewValue(), $phids);
        break;
    }

    return $phids;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_REQUEST:
        return pht(
          '%s requested %s',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($new));
        break;

      case self::TYPE_USER_INTENT:
        return $this->getIntentTitle();
        break;

      case self::TYPE_EDIT_FIELD:
        $field = newv($this->getMetadataValue('fieldClass'), array());
        $name = $field->getName();

        $markup = $name;
        if ($this->getRenderingTarget() ===
          parent::TARGET_HTML) {

          $markup = hsprintf('<em>%s</em>', $name);
        }

        return pht(
          '%s changed the %s to "%s"',
          $this->renderHandleLink($author_phid),
          $markup,
          $field->normalizeForTransactionView($this, $new));
        break;

      case self::TYPE_PICK_STATUS:
        switch ($new) {
          case ReleephRequest::PICK_OK:
            return pht('%s found this request picks without error',
              $this->renderHandleLink($author_phid));

          case ReleephRequest::REVERT_OK:
            return pht('%s found this request reverts without error',
              $this->renderHandleLink($author_phid));

          case ReleephRequest::PICK_FAILED:
            return pht("%s couldn't pick this request",
              $this->renderHandleLink($author_phid));

          case ReleephRequest::REVERT_FAILED:
            return pht("%s couldn't revert this request",
              $this->renderHandleLink($author_phid));
        }
        break;

      case self::TYPE_COMMIT:
        $action_type = $this->getMetadataValue('action');
        switch ($action_type) {
          case 'pick':
            return pht(
              '%s picked this request and committed the result upstream',
              $this->renderHandleLink($author_phid));
            break;

          case 'revert':
            return pht(
              '%s reverted this request and committed the result upstream',
              $this->renderHandleLink($author_phid));
            break;
        }
        break;

      case self::TYPE_MANUAL_IN_BRANCH:
        $action = $new ? pht('picked') : pht('reverted');
        return pht(
          '%s marked this request as manually %s',
          $this->renderHandleLink($author_phid),
          $action);
        break;

      case self::TYPE_DISCOVERY:
        return pht('%s discovered this commit as %s',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($new));
        break;

      default:
        return parent::getTitle();
        break;
    }
  }

  public function getActionName() {
    switch ($this->getTransactionType()) {
      case self::TYPE_REQUEST:
        return pht('Requested');

      case self::TYPE_COMMIT:
        $action_type = $this->getMetadataValue('action');
        switch ($action_type) {
          case 'pick':
            return pht('Picked');

          case 'revert':
            return pht('Reverted');
        }
    }

    return parent::getActionName();
  }

  public function getColor() {
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_USER_INTENT:
        switch ($new) {
          case ReleephRequest::INTENT_WANT:
            return PhabricatorTransactions::COLOR_GREEN;
          case ReleephRequest::INTENT_PASS:
            return PhabricatorTransactions::COLOR_RED;
        }
    }
    return parent::getColor();
  }

  private static function searchForPHIDs($thing, array &$phids) {
    /**
     * To implement something like getRequiredHandlePHIDs() in a
     * ReleephFieldSpecification, we'd have to provide the field with its
     * ReleephRequest (so that it could load the PHIDs from the
     * ReleephRequest's storage, and return them.)
     *
     * We don't have fields initialized with their ReleephRequests, but we can
     * make a good guess at what handles will be needed for rendering the field
     * in this transaction by inspecting the old and new values.
     */
    if (!is_array($thing)) {
      $thing = array($thing);
    }

    foreach ($thing as $value) {
      if (phid_get_type($value) !==
        PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN) {

        $phids[] = $value;
      }
    }
  }

  private function getIntentTitle() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $new = $this->getNewValue();
    $is_pusher = $this->getMetadataValue('isPusher');

    switch ($new) {
      case ReleephRequest::INTENT_WANT:
        if ($is_pusher) {
          return pht(
            '%s approved this request',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s wanted this request',
            $this->renderHandleLink($author_phid));
        }

      case ReleephRequest::INTENT_PASS:
        if ($is_pusher) {
          return pht(
            '%s rejected this request',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s passed on this request',
            $this->renderHandleLink($author_phid));
        }
    }
  }

  public function shouldHide() {
    $type = $this->getTransactionType();

    if ($type === self::TYPE_USER_INTENT &&
        $this->getMetadataValue('isRQCreate')) {

      return true;
    }

    if ($this->isBoringPickStatus()) {
      return true;
    }

    // ReleephSummaryFieldSpecification is usually blank when an RQ is created,
    // creating a transaction change from null to "". Hide these!
    if ($type === self::TYPE_EDIT_FIELD) {
      if ($this->getOldValue() === null && $this->getNewValue() === '') {
        return true;
      }
    }
    return parent::shouldHide();
  }

  public function isBoringPickStatus() {
    $type = $this->getTransactionType();
    if ($type === self::TYPE_PICK_STATUS) {
      $new = $this->getNewValue();
      if ($new === ReleephRequest::PICK_OK ||
          $new === ReleephRequest::REVERT_OK) {

        return true;
      }
    }
    return false;
  }

}

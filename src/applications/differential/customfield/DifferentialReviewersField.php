<?php

final class DifferentialReviewersField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:reviewers';
  }

  public function getFieldKeyForConduit() {
    return 'reviewerPHIDs';
  }

  public function getFieldName() {
    return pht('Reviewers');
  }

  public function getFieldDescription() {
    return pht('Manage reviewers.');
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {
    return $revision->getReviewerStatus();
  }

  public function getNewValueForApplicationTransactions() {
    $specs = array();
    foreach ($this->getValue() as $reviewer) {
      $specs[$reviewer->getReviewerPHID()] = array(
        'data' => $reviewer->getEdgeData(),
      );
    }

    return array('=' => $specs);
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $datasource = id(new DifferentialBlockingReviewerDatasource())
      ->setViewer($request->getViewer());

    $new_phids = $request->getArr($this->getFieldKey());
    $new_phids = $datasource->evaluateTokens($new_phids);

    $reviewers = array();
    foreach ($new_phids as $spec) {
      if (!is_array($spec)) {
        $reviewers[$spec] = DifferentialReviewerStatus::STATUS_ADDED;
      } else {
        $reviewers[$spec['phid']] = $spec['type'];
      }
    }

    $this->updateReviewers($this->getValue(), $reviewers);
  }

  private function updateReviewers(array $old_reviewers, array $new_map) {
    // Compute a new set of reviewer objects. We're going to respect the new
    // reviewer order, add or remove any new or missing reviewers, and respect
    // any blocking or unblocking changes. For reviewers who were there before
    // and are still there, we're going to keep the old value because it
    // may be something like "Accept", "Reject", etc.

    $old_map = mpull($old_reviewers, 'getStatus', 'getReviewerPHID');
    $status_blocking = DifferentialReviewerStatus::STATUS_BLOCKING;

    $new_reviewers = array();
    foreach ($new_map as $phid => $new) {
      $old = idx($old_map, $phid);

      // If we have an old status and this didn't make the reviewer blocking
      // or nonblocking, just retain the old status. This makes sure we don't
      // throw away rejects, accepts, etc.
      if ($old) {
        $is_block = ($old !== $status_blocking && $new === $status_blocking);
        $is_unblock = ($old === $status_blocking && $new !== $status_blocking);
        if (!$is_block && !$is_unblock) {
          $new_reviewers[$phid] = $old;
          continue;
        }
      }

      $new_reviewers[$phid] = $new;
    }

    foreach ($new_reviewers as $phid => $status) {
      $new_reviewers[$phid] = new DifferentialReviewer(
        $phid,
        array(
          'status' => $status,
        ));
    }

    $this->setValue($new_reviewers);
  }

  public function renderEditControl(array $handles) {
    $status_blocking = DifferentialReviewerStatus::STATUS_BLOCKING;

    $value = array();
    foreach ($this->getValue() as $reviewer) {
      $phid = $reviewer->getReviewerPHID();
      if ($reviewer->getStatus() == $status_blocking) {
        $value[] = 'blocking('.$phid.')';
      } else {
        $value[] = $phid;
      }
    }

    return id(new AphrontFormTokenizerControl())
      ->setUser($this->getViewer())
      ->setName($this->getFieldKey())
      ->setDatasource(new DifferentialReviewerDatasource())
      ->setValue($value)
      ->setError($this->getFieldError())
      ->setLabel($this->getFieldName());
  }

  public function getApplicationTransactionType() {
    return PhabricatorTransactions::TYPE_EDGE;
  }

  public function getApplicationTransactionMetadata() {
    return array(
      'edge:type' => DifferentialRevisionHasReviewerEdgeType::EDGECONST,
    );
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    return mpull($this->getUserReviewers(), 'getReviewerPHID');
  }

  public function renderPropertyViewValue(array $handles) {
    $reviewers = $this->getUserReviewers();
    if (!$reviewers) {
      return phutil_tag('em', array(), pht('None'));
    }

    $view = id(new DifferentialReviewersView())
      ->setUser($this->getViewer())
      ->setReviewers($reviewers)
      ->setHandles($handles);

    // TODO: Active diff stuff.

    return $view;
  }

  private function getUserReviewers() {
    $reviewers = array();
    foreach ($this->getObject()->getReviewerStatus() as $reviewer) {
      if ($reviewer->isUser()) {
        $reviewers[] = $reviewer;
      }
    }
    return $reviewers;
  }

  public function shouldAppearInCommitMessage() {
    return true;
  }

  public function shouldAppearInCommitMessageTemplate() {
    return true;
  }

  public function getCommitMessageLabels() {
    return array(
      'Reviewer',
      'Reviewers',
    );
  }

  public function parseValueFromCommitMessage($value) {
    $results = $this->parseObjectList(
      $value,
      array(
        PhabricatorPeopleUserPHIDType::TYPECONST,
        PhabricatorProjectProjectPHIDType::TYPECONST,
        PhabricatorOwnersPackagePHIDType::TYPECONST,
      ),
      false,
      array('!'));

    return $this->flattenReviewers($results);
  }

  public function getRequiredHandlePHIDsForCommitMessage() {
    return mpull($this->getValue(), 'getReviewerPHID');
  }

  public function readValueFromCommitMessage($value) {
    $value = $this->inflateReviewers($value);

    $reviewers = array();
    foreach ($value as $spec) {
      $phid = $spec['phid'];

      $is_blocking = isset($spec['suffixes']['!']);
      if ($is_blocking) {
        $status = DifferentialReviewerStatus::STATUS_BLOCKING;
      } else {
        $status = DifferentialReviewerStatus::STATUS_ADDED;
      }

      $reviewers[$phid] = $status;
    }

    $this->updateReviewers(
      $this->getObject()->getReviewerStatus(),
      $reviewers);

    return $this;
  }

  public function renderCommitMessageValue(array $handles) {
    $suffixes = array();

    $status_blocking = DifferentialReviewerStatus::STATUS_BLOCKING;

    foreach ($this->getValue() as $reviewer) {
      if ($reviewer->getStatus() == $status_blocking) {
        $phid = $reviewer->getReviewerPHID();
        $suffixes[$phid] = '!';
      }
    }

    return $this->renderObjectList($handles, $suffixes);
  }

  public function validateCommitMessageValue($value) {
    if (!$value) {
      return;
    }

    $author_phid = $this->getObject()->getAuthorPHID();

    $config_self_accept_key = 'differential.allow-self-accept';
    $allow_self_accept = PhabricatorEnv::getEnvConfig($config_self_accept_key);

    $value = $this->inflateReviewers($value);
    foreach ($value as $spec) {
      $phid = $spec['phid'];

      if (($phid == $author_phid) && !$allow_self_accept) {
        throw new DifferentialFieldValidationException(
          pht('The author of a revision can not be a reviewer.'));
      }
    }
  }

  public function getRequiredHandlePHIDsForRevisionHeaderWarnings() {
    return mpull($this->getValue(), 'getReviewerPHID');
  }

  public function getWarningsForRevisionHeader(array $handles) {
    $revision = $this->getObject();

    $status_needs_review = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;
    if ($revision->getStatus() != $status_needs_review) {
      return array();
    }

    foreach ($this->getValue() as $reviewer) {
      if (!$handles[$reviewer->getReviewerPHID()]->isDisabled()) {
        return array();
      }
    }

    $warnings = array();
    if ($this->getValue()) {
      $warnings[] = pht(
        'This revision needs review, but all specified reviewers are '.
        'disabled or inactive.');
    } else {
      $warnings[] = pht(
        'This revision needs review, but there are no reviewers specified.');
    }

    return $warnings;
  }

  public function getProTips() {
    return array(
      pht(
        'You can mark a reviewer as blocking by adding an exclamation '.
        'mark ("!") after their name.'),
    );
  }

  private function flattenReviewers(array $values) {
    // NOTE: For now, `arc` relies on this field returning only scalars, so we
    // need to reduce the results into scalars. See T10981.
    $result = array();

    foreach ($values as $value) {
      $result[] = $value['phid'].implode('', array_keys($value['suffixes']));
    }

    return $result;
  }

  private function inflateReviewers(array $values) {
    $result = array();

    foreach ($values as $value) {
      if (substr($value, -1) == '!') {
        $value = substr($value, 0, -1);
        $suffixes = array('!' => '!');
      } else {
        $suffixes = array();
      }

      $result[] = array(
        'phid' => $value,
        'suffixes' => $suffixes,
      );
    }

    return $result;
  }

}

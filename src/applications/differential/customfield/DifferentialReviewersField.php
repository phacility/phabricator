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
    // Compute a new set of reviewer objects. We're going to respect the new
    // reviewer order, add or remove any missing or new reviewers, and respect
    // any blocking or unblocking changes. For reviewers who were there before
    // and are still there, we're going to keep the current value because it
    // may be something like "Accept", "Reject", etc.

    $old_status = $this->getValue();
    $old_status = mpull($old_status, 'getStatus', 'getReviewerPHID');

    $datasource = id(new DifferentialBlockingReviewerDatasource())
      ->setViewer($request->getViewer());

    $new_phids = $request->getArr($this->getFieldKey());
    $new_phids = $datasource->evaluateTokens($new_phids);

    $status_blocking = DifferentialReviewerStatus::STATUS_BLOCKING;

    $specs = array();
    foreach ($new_phids as $spec) {
      if (!is_array($spec)) {
        $spec = array(
          'type' => DifferentialReviewerStatus::STATUS_ADDED,
          'phid' => $spec,
        );
      }
      $specs[$spec['phid']] = $spec;
    }

    $new_status = array();
    foreach ($specs as $phid => $spec) {
      $new = $spec['type'];
      $old = idx($old_status, $phid);

      // If we have an old status and this didn't make the reviewer blocking
      // or nonblocking, just retain the old status. This makes sure we don't
      // throw away rejects, accepts, etc.
      if ($old) {
        $is_block = ($old !== $status_blocking && $new === $status_blocking);
        $is_unblock = ($old === $status_blocking && $new !== $status_blocking);
        if (!$is_block && !$is_unblock) {
          $new_status[$phid] = $old;
          continue;
        }
      }

      $new_status[$phid] = $new;
    }

    foreach ($new_status as $phid => $status) {
      $new_status[$phid] = new DifferentialReviewer(
        $phid,
        array(
          'status' => $status,
        ));
    }

    $this->setValue($new_status);
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
    return $this->parseObjectList(
      $value,
      array(
        PhabricatorPeopleUserPHIDType::TYPECONST,
        PhabricatorProjectProjectPHIDType::TYPECONST,
        PhabricatorOwnersPackagePHIDType::TYPECONST,
      ));
  }

  public function getRequiredHandlePHIDsForCommitMessage() {
    return mpull($this->getValue(), 'getReviewerPHID');
  }

  public function readValueFromCommitMessage($value) {
    $current_reviewers = $this->getObject()->getReviewerStatus();
    $current_reviewers = mpull($current_reviewers, null, 'getReviewerPHID');

    $reviewers = array();
    foreach ($value as $phid) {
      $reviewer = idx($current_reviewers, $phid);
      if ($reviewer) {
        $reviewers[] = $reviewer;
      } else {
        $data = array(
          'status' => DifferentialReviewerStatus::STATUS_ADDED,
        );
        $reviewers[] = new DifferentialReviewer($phid, $data);
      }
    }

    $this->setValue($reviewers);

    return $this;
  }

  public function renderCommitMessageValue(array $handles) {
    return $this->renderObjectList($handles);
  }

  public function validateCommitMessageValue($value) {
    $author_phid = $this->getObject()->getAuthorPHID();

    $config_self_accept_key = 'differential.allow-self-accept';
    $allow_self_accept = PhabricatorEnv::getEnvConfig($config_self_accept_key);

    foreach ($value as $phid) {
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

}

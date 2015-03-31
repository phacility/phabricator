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
    // Compute a new set of reviewer objects. For reviewers who haven't been
    // added or removed, retain their existing status. Also, respect the new
    // order.

    $old_status = $this->getValue();
    $old_status = mpull($old_status, null, 'getReviewerPHID');

    $new_phids = $request->getArr($this->getFieldKey());
    $new_phids = array_fuse($new_phids);

    $new_status = array();
    foreach ($new_phids as $new_phid) {
      if (empty($old_status[$new_phid])) {
        $new_status[$new_phid] = new DifferentialReviewer(
          $new_phid,
          array(
            'status' => DifferentialReviewerStatus::STATUS_ADDED,
          ));
      } else {
        $new_status[$new_phid] = $old_status[$new_phid];
      }
    }

    $this->setValue($new_status);
  }

  public function renderEditControl(array $handles) {
    $phids = array();
    if ($this->getValue()) {
      $phids = mpull($this->getValue(), 'getReviewerPHID');
    }

    return id(new AphrontFormTokenizerControl())
      ->setUser($this->getViewer())
      ->setName($this->getFieldKey())
      ->setDatasource(new PhabricatorProjectOrUserDatasource())
      ->setValue($phids)
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

<?php

final class DifferentialReviewersField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:reviewers';
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

  public function getRequiredHandlePHIDsForEdit() {
    return mpull($this->getValue(), 'getReviewerPHID');
  }

  public function renderEditControl(array $handles) {
    return id(new AphrontFormTokenizerControl())
      ->setName($this->getFieldKey())
      ->setDatasource('/typeahead/common/usersorprojects/')
      ->setValue($handles)
      ->setError($this->getFieldError())
      ->setLabel($this->getFieldName());
  }

  public function getApplicationTransactionType() {
    return PhabricatorTransactions::TYPE_EDGE;
  }

  public function getApplicationTransactionMetadata() {
    return array(
      'edge:type' => PhabricatorEdgeConfig::TYPE_DREV_HAS_REVIEWER,
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
}

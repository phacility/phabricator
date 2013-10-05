<?php

final class DifferentialReviewersFieldSpecification
  extends DifferentialFieldSpecification {

  private $reviewers = array();
  private $error;

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function getRequiredHandlePHIDsForRevisionView() {
    return $this->getReviewerPHIDs();
  }

  public function renderLabelForRevisionView() {
    return 'Reviewers:';
  }

  public function renderValueForRevisionView() {
    if (!$this->getReviewerPHIDs()) {
      // Renders "None".
      return $this->renderUserList(array());
    }

    $revision = $this->getRevision();
    $reviewers = $revision->getReviewerStatus();


    $diff = $revision->loadActiveDiff();
    if ($diff) {
      $diff = $diff->getID();
    }

    $view = new PHUIStatusListView();
    $handles = $this->getLoadedHandles();
    foreach ($reviewers as $reviewer) {
      $phid = $reviewer->getReviewerPHID();

      $is_current = (!$diff) ||
                    (!$reviewer->getDiffID()) ||
                    ($diff == $reviewer->getDiffID());

      $item = new PHUIStatusItemView();

      switch ($reviewer->getStatus()) {
        case DifferentialReviewerStatus::STATUS_ADDED:
          $item->setIcon('open-dark', pht('Review Requested'));
          break;

        case DifferentialReviewerStatus::STATUS_ACCEPTED:
          if ($is_current) {
            $item->setIcon(
              'accept-green',
              pht('Accept'));
          } else {
            $item->setIcon(
              'accept-dark',
              pht('Accepted Prior Diff'));
          }
          break;

        case DifferentialReviewerStatus::STATUS_REJECTED:
          if ($is_current) {
            $item->setIcon(
              'reject-red',
              pht('Requested Changes'));
          } else {
            $item->setIcon(
              'reject-dark',
              pht('Requested Changes to Prior Diff'));
          }
          break;

        case DifferentialReviewerStatus::STATUS_COMMENTED:
          if ($is_current) {
            $item->setIcon(
              'info-blue',
              pht('Commented'));
          } else {
            $item->setIcon(
              'info-dark',
              pht('Commented Previously'));
          }
          break;

      }

      $item->setTarget($handles[$phid]->renderLink());
      $view->addItem($item);
    }
    return $view;
  }

  private function getReviewerPHIDs() {
    $revision = $this->getRevision();
    return $revision->getReviewers();
  }

  public function shouldAppearOnEdit() {
    return true;
  }

  protected function didSetRevision() {
    $this->reviewers = $this->getReviewerPHIDs();
  }

  public function getRequiredHandlePHIDsForRevisionEdit() {
    return $this->reviewers;
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->reviewers = $request->getArr('reviewers');
    return $this;
  }

  public function validateField() {
    if (!$this->hasRevision()) {
      return;
    }

    $self = PhabricatorEnv::getEnvConfig('differential.allow-self-accept');
    if ($self) {
      return;
    }

    $author_phid = $this->getRevision()->getAuthorPHID();
    if (!in_array($author_phid, $this->reviewers)) {
      return;
    }

    $this->error = 'Invalid';
    throw new DifferentialFieldValidationException(
      "The owner of a revision may not be a reviewer.");
  }

  public function renderEditControl() {
    $reviewer_map = array();
    foreach ($this->reviewers as $phid) {
      $reviewer_map[$phid] = $this->getHandle($phid)->getFullName();
    }
    return id(new AphrontFormTokenizerControl())
      ->setLabel(pht('Reviewers'))
      ->setName('reviewers')
      ->setUser($this->getUser())
      ->setDatasource('/typeahead/common/users/')
      ->setValue($reviewer_map)
      ->setError($this->error);
  }

  public function willWriteRevision(DifferentialRevisionEditor $editor) {
    $editor->setReviewers($this->reviewers);
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function getCommitMessageKey() {
    return 'reviewerPHIDs';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->reviewers = array_unique(nonempty($value, array()));
    return $this;
  }

  public function renderLabelForCommitMessage() {
    return 'Reviewers';
  }

  public function getRequiredHandlePHIDsForCommitMessage() {
    return $this->reviewers;
  }

  public function renderValueForCommitMessage($is_edit) {
    if (!$this->reviewers) {
      return null;
    }

    $names = array();
    foreach ($this->reviewers as $phid) {
      $names[] = $this->getHandle($phid)->getName();
    }

    return implode(', ', $names);
  }

  public function getSupportedCommitMessageLabels() {
    return array(
      'Reviewer',
      'Reviewers',
    );
  }

  public function parseValueFromCommitMessage($value) {
    return $this->parseCommitMessageUserList($value);
  }

  public function shouldAppearOnRevisionList() {
    return true;
  }

  public function renderHeaderForRevisionList() {
    return 'Reviewers';
  }

  public function renderValueForRevisionList(DifferentialRevision $revision) {
    $primary_reviewer = $revision->getPrimaryReviewer();
    if ($primary_reviewer) {
      $names = array();

      foreach ($revision->getReviewers() as $reviewer) {
        $names[] = $this->getHandle($reviewer)->renderLink();
      }

      return phutil_implode_html(', ', $names);
    } else {
      return phutil_tag('em', array(), 'None');
    }
  }

  public function getRequiredHandlePHIDsForRevisionList(
    DifferentialRevision $revision) {
    return $revision->getReviewers();
  }

  public function renderValueForMail($phase) {
    if ($phase == DifferentialMailPhase::COMMENT) {
      return null;
    }

    if (!$this->reviewers) {
      return null;
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getUser())
      ->withPHIDs($this->reviewers)
      ->execute();
    $handles = array_select_keys(
      $handles,
      array($this->getRevision()->getPrimaryReviewer())) + $handles;
    $names = mpull($handles, 'getName');
    return 'Reviewers: '.implode(', ', $names);
  }

}

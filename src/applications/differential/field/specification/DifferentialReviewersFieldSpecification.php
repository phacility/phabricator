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
    return $this->renderUserList($this->getReviewerPHIDs());
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
      ->setLabel('Reviewers')
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
    $this->reviewers = nonempty($value, array());
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
      $other_reviewers = array_flip($revision->getReviewers());
      unset($other_reviewers[$primary_reviewer]);
      if ($other_reviewers) {
        $names = array();
        foreach ($other_reviewers as $reviewer => $_) {
          $names[] = phutil_escape_html(
            $this->getHandle($reviewer)->getLinkName());
        }
        $suffix = ' '.javelin_render_tag(
          'abbr',
          array(
            'sigil' => 'has-tooltip',
            'meta'  => array(
              'tip'   => implode(', ', $names),
              'align' => 'E',
            ),
          ),
          '(+'.(count($names)).')');
      } else {
        $suffix = null;
      }
      return $this->getHandle($primary_reviewer)->renderLink().$suffix;
    } else {
      return '<em>None</em>';
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

    $handles = id(new PhabricatorObjectHandleData($this->reviewers))
      ->loadHandles();
    $handles = array_select_keys(
      $handles,
      array($this->getRevision()->getPrimaryReviewer())) + $handles;
    $names = mpull($handles, 'getName');
    return 'Reviewers: '.implode(', ', $names);
  }

}

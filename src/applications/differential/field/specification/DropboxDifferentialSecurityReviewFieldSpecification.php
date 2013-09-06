<?php
final class DropboxDifferentialSecurityReviewFieldSpecification extends DifferentialFieldSpecification {
  private $dropboxSecurityReview;

  public function getStorageKey() {
    return 'dropbox.security-review';
  }

  public function setValueFromStorage($value) {
    $this->dropboxSecurityReview = $value;
  }

  public function getValueForStorage() {
    return $this->dropboxSecurityReview;
  }

  public function shouldAppearOnEdit() {
    return false;
  }

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return null;
  }

  public function renderValueForRevisionView() {
    return phutil_tag('strong', array(), 'This revision requires a security review');
  }

  public function renderWarningBoxForRevisionAccept() {
    return id(new AphrontErrorView())
      ->setSeverity(AphrontErrorView::SEVERITY_ERROR)
      ->appendChild(phutil_tag('p', array(), 'This revision must be reviewed by a member of the Security team'))
      ->setTitle('Security Review Required');
  }
}

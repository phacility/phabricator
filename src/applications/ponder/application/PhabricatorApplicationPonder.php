<?php

final class PhabricatorApplicationPonder extends PhabricatorApplication {

  public function getBaseURI() {
    return '/ponder/';
  }

  public function getShortDescription() {
    return 'Find Answers';
  }

  public function getIconName() {
    return 'ponder';
  }

  public function getFactObjectsForAnalysis() {
    return array(
      new PonderQuestion(),
    );
  }

  public function getTitleGlyph() {
    return "\xE2\x97\xB3";
  }

  public function loadStatus(PhabricatorUser $user) {
    // replace with "x new unanswered questions" or some such
    $status = array();

    return $status;
  }

  public function getRemarkupRules() {
    return array(
      new PonderRemarkupRule(),
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_COMMUNICATION;
  }

  public function isBeta() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/Q(?P<id>[1-9]\d*)' => 'PonderQuestionViewController',
      '/ponder/' => array(
        '(?P<page>feed/)?' => 'PonderFeedController',
        '(?P<page>questions)/' => 'PonderFeedController',
        '(?P<page>answers)/' => 'PonderFeedController',
        'answer/add/' => 'PonderAnswerSaveController',
        'answer/preview/' => 'PonderAnswerPreviewController',
        'question/ask/' => 'PonderQuestionAskController',
        'question/preview/' => 'PonderQuestionPreviewController',
        'comment/add/' => 'PonderCommentSaveController',
        '(?P<kind>question)/vote/' => 'PonderVoteSaveController',
        '(?P<kind>answer)/vote/' => 'PonderVoteSaveController'
      ),
    );
  }

}

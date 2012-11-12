<?php

final class PhabricatorApplicationPonder extends PhabricatorApplication {

  public function getBaseURI() {
    return '/ponder/';
  }

  public function getShortDescription() {
    return 'Find Answers';
  }

  public function getAutospriteName() {
    return 'ponder';
  }

  public function getFactObjectsForAnalysis() {
    return array(
      new PonderQuestion(),
    );
  }

  public function loadStatus(PhabricatorUser $user) {
    // replace with "x new unanswered questions" or some such
    $status = array();

    return $status;
  }

  public function getApplicationGroup() {
    return self::GROUP_COMMUNICATION;
  }

  public function getroutes() {
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
      ));
  }
}


<?php

final class PhabricatorPonderApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/ponder/';
  }

  public function getName() {
    return pht('Ponder');
  }

  public function getShortDescription() {
    return pht('Questions and Answers');
  }

  public function getFontIcon() {
    return 'fa-university';
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
    // make sure to use self::formatStatusCount and friends...!
    $status = array();

    return $status;
  }

  public function getRemarkupRules() {
    return array(
      new PonderRemarkupRule(),
    );
  }

  public function isPrototype() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/Q(?P<id>[1-9]\d*)' => 'PonderQuestionViewController',
      '/ponder/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?' => 'PonderQuestionListController',
        'answer/add/' => 'PonderAnswerSaveController',
        'answer/edit/(?P<id>\d+)/' => 'PonderAnswerEditController',
        'answer/comment/(?P<id>\d+)/' => 'PonderAnswerCommentController',
        'answer/history/(?P<id>\d+)/' => 'PonderAnswerHistoryController',
        'question/edit/(?:(?P<id>\d+)/)?' => 'PonderQuestionEditController',
        'question/comment/(?P<id>\d+)/' => 'PonderQuestionCommentController',
        'question/history/(?P<id>\d+)/' => 'PonderQuestionHistoryController',
        'preview/' => 'PhabricatorMarkupPreviewController',
        'question/(?P<status>open|close)/(?P<id>[1-9]\d*)/'
          => 'PonderQuestionStatusController',
        'vote/' => 'PonderVoteSaveController',
      ),
    );
  }

}

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

  public function getIcon() {
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

  public function getRemarkupRules() {
    return array(
      new PonderRemarkupRule(),
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function supportsEmailIntegration() {
    return true;
  }

  public function getAppEmailBlurb() {
    return pht(
      'Send email to these addresses to create questions. %s',
      phutil_tag(
        'a',
        array(
          'href' => $this->getInboundEmailSupportLink(),
        ),
        pht('Learn More')));
  }

  public function getRoutes() {
    return array(
      '/Q(?P<id>[1-9]\d*)'
        => 'PonderQuestionViewController',
      '/ponder/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PonderQuestionListController',
        'answer/add/'
          => 'PonderAnswerSaveController',
        'answer/edit/(?P<id>\d+)/'
          => 'PonderAnswerEditController',
        'answer/comment/(?P<id>\d+)/'
          => 'PonderAnswerCommentController',
        'answer/history/(?P<id>\d+)/'
          => 'PonderAnswerHistoryController',
        'question/edit/(?:(?P<id>\d+)/)?'
          => 'PonderQuestionEditController',
        'question/create/'
          => 'PonderQuestionEditController',
        'question/comment/(?P<id>\d+)/'
          => 'PonderQuestionCommentController',
        'question/history/(?P<id>\d+)/'
          => 'PonderQuestionHistoryController',
        'preview/'
          => 'PhabricatorMarkupPreviewController',
        'question/status/(?P<id>[1-9]\d*)/'
          => 'PonderQuestionStatusController',
      ),
    );
  }

  public function getMailCommandObjects() {
    return array(
      'question' => array(
        'name' => pht('Email Commands: Questions'),
        'header' => pht('Interacting with Ponder Questions'),
        'object' => new PonderQuestion(),
        'summary' => pht(
          'This page documents the commands you can use to interact with '.
          'questions in Ponder.'),
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PonderDefaultViewCapability::CAPABILITY => array(
        'template' => PonderQuestionPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      PonderModerateCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
        'template' => PonderQuestionPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
      ),
    );
  }

  public function getApplicationSearchDocumentTypes() {
    return array(
      PonderQuestionPHIDType::TYPECONST,
    );
  }

}

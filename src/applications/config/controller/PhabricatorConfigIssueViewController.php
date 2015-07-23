<?php

final class PhabricatorConfigIssueViewController
  extends PhabricatorConfigController {

  private $issueKey;

  public function willProcessRequest(array $data) {
    $this->issueKey = $data['key'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $issues = PhabricatorSetupCheck::runAllChecks();
    PhabricatorSetupCheck::setOpenSetupIssueKeys(
      PhabricatorSetupCheck::getUnignoredIssueKeys($issues));

    if (empty($issues[$this->issueKey])) {
      $content = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->setTitle(pht('Issue Resolved'))
        ->appendChild(pht('This setup issue has been resolved. '))
        ->appendChild(
          phutil_tag(
            'a',
            array(
              'href' => $this->getApplicationURI('issue/'),
            ),
            pht('Return to Open Issue List')));
      $title = pht('Resolved Issue');
    } else {
      $issue = $issues[$this->issueKey];
      $content = $this->renderIssue($issue);
      $title = $issue->getShortName();
    }

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->setBorder(true)
      ->addTextCrumb(pht('Setup Issues'), $this->getApplicationURI('issue/'))
      ->addTextCrumb($title, $request->getRequestURI());

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
      array(
        'title' => $title,
      ));
  }

  private function renderIssue(PhabricatorSetupIssue $issue) {
    require_celerity_resource('setup-issue-css');

    $view = new PhabricatorSetupIssueView();
    $view->setIssue($issue);

    $container = phutil_tag(
      'div',
      array(
        'class' => 'setup-issue-background',
      ),
      $view->render());

    return $container;
  }

}

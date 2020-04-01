<?php

final class PhabricatorConfigIssueViewController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $issue_key = $request->getURIData('key');

    $engine = new PhabricatorSetupEngine();
    $response = $engine->execute();
    if ($response) {
      return $response;
    }
    $issues = $engine->getIssues();

    if (empty($issues[$issue_key])) {
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
      $issue = $issues[$issue_key];
      $content = $this->renderIssue($issue);
      $title = $issue->getShortName();
    }

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Setup Issues'), $this->getApplicationURI('issue/'))
      ->addTextCrumb($title, $request->getRequestURI())
      ->setBorder(true);

    $launcher_view = id(new PHUILauncherView())
      ->appendChild($content);

    $content = id(new PHUITwoColumnView())
      ->setFooter($launcher_view);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($content);
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

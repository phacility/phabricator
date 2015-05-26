<?php

final class PhabricatorSetupIssueUIExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Setup Issue');
  }

  public function getDescription() {
    return pht('Setup errors and warnings.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $issue = id(new PhabricatorSetupIssue())
      ->setShortName(pht('Short Name'))
      ->setName(pht('Name'))
      ->setSummary(pht('Summary'))
      ->setMessage(pht('Message'))
      ->setIssueKey('example.key')
      ->addCommand('$ # Add Command')
      ->addCommand(hsprintf('<tt>$</tt> %s', '$ ls -1 > /dev/null'))
      ->addPHPConfig('php.config.example')
      ->addPhabricatorConfig('test.value')
      ->addPHPExtension('libexample');

    // NOTE: Since setup issues may be rendered before we can build the page
    // chrome, they don't explicitly include resources.
    require_celerity_resource('setup-issue-css');

    $view = id(new PhabricatorSetupIssueView())
      ->setIssue($issue);

    return $view;
  }
}

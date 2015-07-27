<?php

final class PhabricatorConfigIgnoreController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $issue = $request->getURIData('key');
    $verb = $request->getURIData('verb');

    $issue_uri = $this->getApplicationURI('issue/'.$issue.'/');

    if ($request->isDialogFormPost()) {
      $this->manageApplication($issue);
      return id(new AphrontRedirectResponse())->setURI($issue_uri);
    }

    if ($verb == 'ignore') {
      $title = pht('Really ignore this setup issue?');
      $submit_title = pht('Ignore');
      $body = pht(
        "You can ignore an issue if you don't want to fix it, or plan to ".
        "fix it later. Ignored issues won't appear on every page but will ".
        "still be shown in the list of open issues.");
    } else if ($verb == 'unignore') {
      $title = pht('Unignore this setup issue?');
      $submit_title = pht('Unignore');
      $body = pht(
        'This issue will no longer be suppressed, and will return to its '.
        'rightful place as a global setup warning.');
    } else {
      throw new Exception(pht('Unrecognized verb: %s', $verb));
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($request->getUser())
      ->setTitle($title)
      ->appendChild($body)
      ->addSubmitButton($submit_title)
      ->addCancelButton($issue_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  public function manageApplication($issue) {
    $key = 'config.ignore-issues';
    $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
    $list = $config_entry->getValue();

    if (isset($list[$issue])) {
      unset($list[$issue]);
    } else {
      $list[$issue] = true;
    }

    PhabricatorConfigEditor::storeNewValue(
      $this->getRequest()->getUser(),
      $config_entry,
      $list,
      PhabricatorContentSource::newFromRequest($this->getRequest()));
  }

}

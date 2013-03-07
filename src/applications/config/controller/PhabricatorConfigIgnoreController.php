<?php

final class PhabricatorConfigIgnoreController
  extends PhabricatorApplicationsController {

  private $verb;
  private $issue;

  public function willProcessRequest(array $data) {
    $this->verb = $data['verb'];
    $this->issue = $data['key'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $issue_uri = $this->getApplicationURI('issue');

    if ($request->isDialogFormPost()) {
      $this->manageApplication();
      return id(new AphrontRedirectResponse())->setURI($issue_uri);
    }

    // User just clicked the link, so show them the dialog.
    if ($this->verb == 'ignore') {
      $title = pht('Really ignore this setup issue?');
      $submit_title = pht('Ignore');
    } else if ($this->verb == 'unignore') {
      $title = pht('Really unignore this setup issue?');
      $submit_title = pht('Unignore');
    } else {
      throw new Exception('Unrecognized verb: ' . $this->verb);
    }
    $dialog = new AphrontDialogView();
    $dialog->setTitle($title)
           ->setUser($request->getUser())
           ->addSubmitButton($submit_title)
           ->addCancelButton($issue_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  public function manageApplication() {
    $key = 'config.ignore-issues';
    $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
    $list = $config_entry->getValue();

    if (isset($list[$this->issue])) {
      unset($list[$this->issue]);
    } else {
      $list[$this->issue] = true;
    }

    PhabricatorConfigEditor::storeNewValue(
     $config_entry, $list, $this->getRequest());
  }

}

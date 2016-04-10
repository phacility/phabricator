<?php

final class PhabricatorSystemReadOnlyController
  extends PhabricatorController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $reason = $request->getURIData('reason');

    $body = array();
    switch ($reason) {
      case PhabricatorEnv::READONLY_CONFIG:
        $title = pht('Administrative Read-Only Mode');
        $body[] = pht(
          'An administrator has placed Phabricator into read-only mode.');
        $body[] = pht(
          'This mode may be used to perform temporary maintenance, test '.
          'configuration, or archive an installation permanently.');
        $body[] = pht(
          'Read-only mode was enabled by the explicit action of a human '.
          'administrator, so you can get more information about why it '.
          'has been turned on by rolling your chair away from your desk and '.
          'yelling "Hey! Why is Phabricator in read-only mode??!" using '.
          'your very loudest outside voice.');
        $button = pht('Wait Patiently');
        break;
      default:
        return new Aphront404Response();
    }

    $body[] = pht(
      'In read-only mode you can read existing information, but you will not '.
      'be able to edit information or create new information until this mode '.
      'is disabled.');

    $dialog = $this->newDialog()
      ->setTitle($title)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->addCancelButton('/', $button);

    foreach ($body as $paragraph) {
      $dialog->appendParagraph($paragraph);
    }

    return $dialog;
  }
}

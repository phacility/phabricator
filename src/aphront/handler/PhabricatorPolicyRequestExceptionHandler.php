<?php

final class PhabricatorPolicyRequestExceptionHandler
  extends PhabricatorRequestExceptionHandler {

  public function getRequestExceptionHandlerPriority() {
    return 320000;
  }

  public function getRequestExceptionHandlerDescription() {
    return pht(
      'Handles policy exceptions which occur when a user tries to '.
      'do something they do not have permission to do.');
  }

  public function canHandleRequestException(
    AphrontRequest $request,
    Exception $ex) {

    if (!$this->isPhabricatorSite($request)) {
      return false;
    }

    return ($ex instanceof PhabricatorPolicyException);
  }

  public function handleRequestException(
    AphrontRequest $request,
    Exception $ex) {

    $viewer = $this->getViewer($request);

    if (!$viewer->isLoggedIn()) {
      // If the user isn't logged in, just give them a login form. This is
      // probably a generally more useful response than a policy dialog that
      // they have to click through to get a login form.
      //
      // Possibly we should add a header here like "you need to login to see
      // the thing you are trying to look at".
      $auth_app_class = 'PhabricatorAuthApplication';
      $auth_app = PhabricatorApplication::getByClass($auth_app_class);

      return id(new PhabricatorAuthStartController())
        ->setRequest($request)
        ->setCurrentApplication($auth_app)
        ->handleRequest($request);
    }

    $content = array(
      phutil_tag(
        'div',
        array(
          'class' => 'aphront-policy-rejection',
        ),
        $ex->getRejection()),
    );

    $list = null;
    if ($ex->getCapabilityName()) {
      $list = $ex->getMoreInfo();
      foreach ($list as $key => $item) {
        $list[$key] = $item;
      }

      $content[] = phutil_tag(
        'div',
        array(
          'class' => 'aphront-capability-details',
        ),
        pht('Users with the "%s" capability:', $ex->getCapabilityName()));

    }

    $dialog = id(new AphrontDialogView())
      ->setTitle($ex->getTitle())
      ->setClass('aphront-access-dialog')
      ->setUser($viewer)
      ->appendChild($content);

    if ($list) {
      $dialog->appendList($list);
    }

    if ($request->isAjax()) {
      $dialog->addCancelButton('/', pht('Close'));
    } else {
      $dialog->addCancelButton('/', pht('OK'));
    }

    return $dialog;
  }

}

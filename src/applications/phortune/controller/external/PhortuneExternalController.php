<?php

abstract class PhortuneExternalController
  extends PhortuneController {

  private $email;

  final public function shouldAllowPublic() {
    return true;
  }

  abstract protected function handleExternalRequest(AphrontRequest $request);

  final protected function hasAccountEmail() {
    return (bool)$this->email;
  }

  final protected function getAccountEmail() {
    return $this->email;
  }

  final protected function getExternalViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  final public function handleRequest(AphrontRequest $request) {
    $address_key = $request->getURIData('addressKey');
    $access_key = $request->getURIData('accessKey');

    $viewer = $this->getViewer();
    $xviewer = $this->getExternalViewer();

    $email = id(new PhortuneAccountEmailQuery())
      ->setViewer($xviewer)
      ->withAddressKeys(array($address_key))
      ->executeOne();
    if (!$email) {
      return new Aphront404Response();
    }

    $account = $email->getAccount();

    $can_see = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $account,
      PhabricatorPolicyCapability::CAN_EDIT);

    $email_display = phutil_tag('strong', array(), $email->getAddress());
    $user_display = phutil_tag('strong', array(), $viewer->getUsername());

    $actual_key = $email->getAccessKey();
    if (!phutil_hashes_are_identical($access_key, $actual_key)) {
      $dialog = $this->newDialog()
        ->setTitle(pht('Email Access Link Out of Date'))
        ->appendParagraph(
          pht(
            'You are trying to access this payment account as: %s',
            $email_display))
        ->appendParagraph(
          pht(
            'The access link you have followed is out of date and no longer '.
            'works.'));

        if ($can_see) {
          $dialog->appendParagraph(
            pht(
              'You are currently logged in as a user (%s) who has '.
              'permission to manage the payment account, so you can '.
              'continue to the updated link.',
              $user_display));

          $dialog->addCancelButton(
            $email->getExternalURI(),
            pht('Continue to Updated Link'));
        } else {
          $dialog->appendParagraph(
            pht(
              'To access information about this payment account, follow '.
              'a more recent link or ask a user with access to give you '.
              'an updated link.'));
        }

      return $dialog;
    }

    switch ($email->getStatus()) {
      case PhortuneAccountEmailStatus::STATUS_ACTIVE:
        break;
      case PhortuneAccountEmailStatus::STATUS_DISABLED:
        return $this->newDialog()
          ->setTitle(pht('Address Disabled'))
          ->appendParagraph(
            pht(
              'This email address (%s) has been disabled and no longer has '.
              'access to this payment account.',
              $email_display));
      case PhortuneAccountEmailStatus::STATUS_UNSUBSCRIBED:
        return $this->newDialog()
          ->setTitle(pht('Permanently Unsubscribed'))
          ->appendParagraph(
            pht(
              'This email address (%s) has been permanently unsubscribed '.
              'and no longer has access to this payment account.',
              $email_display));
        break;
      default:
        return new Aphront404Response();
    }

    $this->email = $email;

    return $this->handleExternalRequest($request);
  }

  final protected function newExternalCrumbs() {
    $viewer = $this->getViewer();

    $crumbs = new PHUICrumbsView();

    if ($this->hasAccountEmail()) {
      $email = $this->getAccountEmail();
      $account = $email->getAccount();

      $crumb_name = pht(
        'Payment Account: %s',
        $account->getName());

      $crumb = id(new PHUICrumbView())
        ->setIcon('fa-diamond')
        ->setName($crumb_name)
        ->setHref($email->getExternalURI());

      $crumbs
        ->addCrumb($crumb);
    } else {
      $crumb = id(new PHUICrumbView())
        ->setIcon('fa-diamond')
        ->setText(pht('External Account View'));

      $crumbs->addCrumb($crumb);
    }

    return $crumbs;
  }

  final protected function newExternalView() {
    $email = $this->getAccountEmail();
    $xviewer = $this->getExternalViewer();

    $origin_phid = $email->getAuthorPHID();

    $handles = $xviewer->loadHandles(array($origin_phid));


    $messages = array();
    $messages[] = pht(
      'You are viewing this payment account as: %s',
      phutil_tag('strong', array(), $email->getAddress()));

    $messages[] = pht(
      'This email address was added to this payment account by: %s',
      phutil_tag('strong', array(), $handles[$origin_phid]->getFullName()));

    $messages[] = pht(
      'Anyone who has a link to this page can view order history for '.
      'this payment account.');

    return id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
      ->setErrors($messages);
  }
}

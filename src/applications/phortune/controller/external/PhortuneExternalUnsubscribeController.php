<?php

final class PhortuneExternalUnsubscribeController
  extends PhortuneExternalController {

  protected function handleExternalRequest(AphrontRequest $request) {
    $xviewer = $this->getExternalViewer();
    $email = $this->getAccountEmail();
    $account = $email->getAccount();

    $email_uri = $email->getExternalURI();

    if ($request->isFormOrHisecPost()) {
      $xactions = array();

      $xactions[] = $email->getApplicationTransactionTemplate()
        ->setTransactionType(
          PhortuneAccountEmailStatusTransaction::TRANSACTIONTYPE)
        ->setNewValue(PhortuneAccountEmailStatus::STATUS_UNSUBSCRIBED);

      $email->getApplicationTransactionEditor()
        ->setActor($xviewer)
        ->setActingAsPHID($email->getPHID())
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->setCancelURI($email_uri)
        ->applyTransactions($email, $xactions);

      return id(new AphrontRedirectResponse())->setURI($email_uri);
    }

    $email_display = phutil_tag(
      'strong',
      array(),
      $email->getAddress());

    $account_display = phutil_tag(
      'strong',
      array(),
      $account->getName());

    $submit = pht(
      'Permanently Unsubscribe (%s)',
      $email->getAddress());

    return $this->newDialog()
      ->setTitle(pht('Permanently Unsubscribe'))
      ->appendParagraph(
        pht(
          'Permanently unsubscribe this email address (%s) from this '.
          'payment account (%s)?',
          $email_display,
          $account_display))
      ->appendParagraph(
        pht(
          'You will no longer receive email and access links will no longer '.
          'function.'))
      ->appendParagraph(
        pht(
          'This action is permanent and can not be undone.'))
      ->addCancelButton($email_uri)
      ->addSubmitButton($submit);

  }

}

<?php

final class PhabricatorApplicationTransactionNoEffectResponse
  extends AphrontProxyResponse {

  private $viewer;
  private $exception;
  private $cancelURI;

  public function setCancelURI($cancel_uri) {
    $this->cancelURI = $cancel_uri;
    return $this;
  }

  public function setException(
    PhabricatorApplicationTransactionNoEffectException $exception) {
    $this->exception = $exception;
    return $this;
  }

  protected function buildProxy() {
    return new AphrontDialogResponse();
  }

  public function reduceProxyResponse() {
    $request = $this->getRequest();

    $ex = $this->exception;
    $xactions = $ex->getTransactions();

    $type_comment = PhabricatorTransactions::TYPE_COMMENT;
    $only_empty_comment = (count($xactions) == 1) &&
      (head($xactions)->getTransactionType() == $type_comment);

    $count = phutil_count($xactions);

    if ($ex->hasAnyEffect()) {
      $title = pht('%s Action(s) With No Effect', $count);
      $head = pht('Some of your %s action(s) have no effect:', $count);
      $tail = pht('Apply remaining actions?');
      $continue = pht('Apply Remaining Actions');
    } else if ($ex->hasComment()) {
      $title = pht('Post as Comment');
      $head = pht('The %s action(s) you are taking have no effect:', $count);
      $tail = pht('Do you want to post your comment anyway?');
      $continue = pht('Post Comment');
    } else if ($only_empty_comment) {
      // Special case this since it's common and we can give the user a nicer
      // dialog than "Action Has No Effect".
      $title = pht('Empty Comment');
      $head = null;
      $tail = null;
      $continue = null;
    } else {
      $title = pht('%s Action(s) Have No Effect', $count);
      $head = pht('The %s action(s) you are taking have no effect:', $count);
      $tail = null;
      $continue = null;
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($request->getUser())
      ->setTitle($title);

    $dialog->appendChild($head);

    $list = array();
    foreach ($xactions as $xaction) {
      $list[] = $xaction->getNoEffectDescription();
    }

    if ($list) {
      $dialog->appendList($list);
    }
    $dialog->appendChild($tail);

    if ($continue) {
      $passthrough = $request->getPassthroughRequestParameters();
      foreach ($passthrough as $key => $value) {
        $dialog->addHiddenInput($key, $value);
      }
      $dialog->addHiddenInput('__continue__', 1);
      $dialog->addSubmitButton($continue);
    }

    $dialog->addCancelButton($this->cancelURI);

    return $this->getProxy()->setDialog($dialog);
  }

}

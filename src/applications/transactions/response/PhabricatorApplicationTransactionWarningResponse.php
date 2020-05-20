<?php

final class PhabricatorApplicationTransactionWarningResponse
  extends AphrontProxyResponse {

  private $viewer;
  private $object;
  private $exception;
  private $cancelURI;

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function setCancelURI($cancel_uri) {
    $this->cancelURI = $cancel_uri;
    return $this;
  }

  public function setException(
    PhabricatorApplicationTransactionWarningException $exception) {
    $this->exception = $exception;
    return $this;
  }

  public function getException() {
    return $this->exception;
  }

  protected function buildProxy() {
    return new AphrontDialogResponse();
  }

  public function reduceProxyResponse() {
    $request = $this->getRequest();
    $viewer = $request->getViewer();
    $object = $this->getObject();

    $xactions = $this->getException()->getTransactions();
    $xaction_groups = mgroup($xactions, 'getTransactionType');

    $warnings = array();
    foreach ($xaction_groups as $xaction_group) {
      $xaction = head($xaction_group);

      $warning = $xaction->newWarningForTransactions(
        $object,
        $xaction_group);

      if (!($warning instanceof PhabricatorTransactionWarning)) {
        throw new Exception(
          pht(
            'Expected "newTransactionWarning()" to return an object of '.
            'class "PhabricatorTransactionWarning", got something else '.
            '("%s") from transaction of class "%s".',
            phutil_describe_type($warning),
            get_class($xaction)));
      }

      $warnings[] = $warning;
    }

    $dialog = id(new AphrontDialogView())
      ->setViewer($viewer);

    $last_key = last_key($warnings);
    foreach ($warnings as $warning_key => $warning) {
      $paragraphs = $warning->getWarningParagraphs();
      foreach ($paragraphs as $paragraph) {
        $dialog->appendParagraph($paragraph);
      }

      if ($warning_key !== $last_key) {
        $dialog->appendChild(phutil_tag('hr'));
      }
    }

    $title_texts = array();
    $continue_texts = array();
    $cancel_texts = array();
    foreach ($warnings as $warning) {
      $title_text = $warning->getTitleText();
      if ($title_text !== null) {
        $title_texts[] = $title_text;
      }

      $continue_text = $warning->getContinueActionText();
      if ($continue_text !== null) {
        $continue_texts[] = $continue_text;
      }

      $cancel_text = $warning->getCancelActionText();
      if ($cancel_text !== null) {
        $cancel_texts[] = $cancel_text;
      }
    }

    $title_texts = array_unique($title_texts);
    if (count($title_texts) === 1) {
      $title = head($title_texts);
    } else {
      $title = pht('Warnings');
    }

    $continue_texts = array_unique($continue_texts);
    if (count($continue_texts) === 1) {
      $continue_action = head($continue_texts);
    } else {
      $continue_action = pht('Continue');
    }

    $cancel_texts = array_unique($cancel_texts);
    if (count($cancel_texts) === 1) {
      $cancel_action = head($cancel_texts);
    } else {
      $cancel_action = null;
    }

    $dialog
      ->setTitle($title)
      ->addSubmitButton($continue_action);

    if ($cancel_action === null) {
      $dialog->addCancelButton($this->cancelURI);
    } else {
      $dialog->addCancelButton($this->cancelURI, $cancel_action);
    }

    $passthrough = $request->getPassthroughRequestParameters();
    foreach ($passthrough as $key => $value) {
      $dialog->addHiddenInput($key, $value);
    }

    $dialog->addHiddenInput('editEngine.warnings', 1);

    return $this->getProxy()->setDialog($dialog);
  }

}

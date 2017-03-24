<?php

abstract class DifferentialRevisionTransactionType
  extends PhabricatorModularTransactionType {

  protected function validateCommitMessageCorpusTransactions(
    $object,
    array $xactions,
    $field_name) {

    $errors = array();
    foreach ($xactions as $xaction) {
      $error = $this->validateMessageCorpus($xaction, $field_name);
      if ($error) {
        $errors[] = $error;
      }
    }

    return $errors;
  }

  private function validateMessageCorpus($xaction, $field_name) {
    $value = $xaction->getNewValue();
    if (!strlen($value)) {
      return null;
    }

    // Put a placeholder title on the message, because the first line of a
    // message is now always parsed as a title.
    $value = "<placeholder>\n".$value;

    $viewer = $this->getActor();
    $parser = DifferentialCommitMessageParser::newStandardParser($viewer);

    // Set custom title and summary keys so we can detect the presence of
    // "Summary:" in, e.g., a test plan.
    $parser->setTitleKey('__title__');
    $parser->setSummaryKey('__summary__');

    $result = $parser->parseCorpus($value);

    unset($result['__title__']);
    unset($result['__summary__']);

    if (!$result) {
      return null;
    }

    return $this->newInvalidError(
      pht(
        'The value you have entered in "%s" can not be parsed '.
        'unambiguously when rendered in a commit message. Edit the '.
        'message so that keywords like "Summary:" and "Test Plan:" do '.
        'not appear at the beginning of lines. Parsed keys: %s.',
        $field_name,
        implode(', ', array_keys($result))),
      $xaction);
  }

  protected function getActiveDiffPHID(DifferentialRevision $revision) {
    try {
      $diff = $revision->getActiveDiff();
      return $diff->getPHID();
    } catch (Exception $ex) {
      return null;
    }
  }

}

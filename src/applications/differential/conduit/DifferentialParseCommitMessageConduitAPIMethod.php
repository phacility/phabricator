<?php

final class DifferentialParseCommitMessageConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  private $errors;

  public function getAPIMethodName() {
    return 'differential.parsecommitmessage';
  }

  public function getMethodDescription() {
    return pht('Parse commit messages for Differential fields.');
  }

  protected function defineParamTypes() {
    return array(
      'corpus'  => 'required string',
      'partial' => 'optional bool',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();
    $corpus = $request->getValue('corpus');
    $is_partial = $request->getValue('partial');

    $field_list = PhabricatorCustomField::getObjectFields(
      new DifferentialRevision(),
      DifferentialCustomField::ROLE_COMMITMESSAGE);
    $field_list->setViewer($viewer);
    $field_map = mpull($field_list->getFields(), null, 'getFieldKeyForConduit');

    $corpus_map = $this->parseCommitMessage($corpus);

    $values = array();
    foreach ($corpus_map as $field_key => $text_value) {
      $field = idx($field_map, $field_key);

      if (!$field) {
        throw new Exception(
          pht(
            'Parser emitted text value for field key "%s", but no such '.
            'field exists.',
            $field_key));
      }

      try {
        $values[$field_key] = $field->parseValueFromCommitMessage($text_value);
      } catch (DifferentialFieldParseException $ex) {
        $this->errors[] = pht(
          'Error parsing field "%s": %s',
          $field->renderCommitMessageLabel(),
          $ex->getMessage());
      }
    }

    if (!$is_partial) {
      foreach ($field_map as $key => $field) {
        try {
          $field->validateCommitMessageValue(idx($values, $key));
        } catch (DifferentialFieldValidationException $ex) {
          $this->errors[] = pht(
            'Invalid or missing field "%s": %s',
            $field->renderCommitMessageLabel(),
            $ex->getMessage());
        }
      }
    }

    // grab some extra information about the Differential Revision: field...
    $revision_id_field = new DifferentialRevisionIDField();
    $revision_id_value = idx(
      $corpus_map,
      $revision_id_field->getFieldKeyForConduit());
    $revision_id_valid_domain = PhabricatorEnv::getProductionURI('');

    return array(
      'errors' => $this->errors,
      'fields' => $values,
      'revisionIDFieldInfo' => array(
        'value' => $revision_id_value,
        'validDomain' => $revision_id_valid_domain,
      ),
    );
  }

  private function parseCommitMessage($corpus) {
    $viewer = $this->getViewer();
    $parser = DifferentialCommitMessageParser::newStandardParser($viewer);
    $result = $parser->parseCorpus($corpus);

    $this->errors = array();
    foreach ($parser->getErrors() as $error) {
      $this->errors[] = $error;
    }

    return $result;
  }

}

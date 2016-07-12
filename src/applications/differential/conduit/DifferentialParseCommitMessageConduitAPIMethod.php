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

    $revision = new DifferentialRevision();

    $field_list = PhabricatorCustomField::getObjectFields(
      $revision,
      DifferentialCustomField::ROLE_COMMITMESSAGE);
    $field_list->setViewer($viewer);
    $field_map = mpull($field_list->getFields(), null, 'getFieldKeyForConduit');

    $this->errors = array();

    $label_map = $this->buildLabelMap($field_list);
    $corpus_map = $this->parseCommitMessage($corpus, $label_map);

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

  private function buildLabelMap(PhabricatorCustomFieldList $field_list) {
    $label_map = array();

    foreach ($field_list->getFields() as $key => $field) {
      $labels = $field->getCommitMessageLabels();
      $key = $field->getFieldKeyForConduit();

      foreach ($labels as $label) {
        $normal_label = DifferentialCommitMessageParser::normalizeFieldLabel(
          $label);
        if (!empty($label_map[$normal_label])) {
          throw new Exception(
            pht(
              'Field label "%s" is parsed by two custom fields: "%s" and '.
              '"%s". Each label must be parsed by only one field.',
              $label,
              $key,
              $label_map[$normal_label]));
        }
        $label_map[$normal_label] = $key;
      }
    }

    return $label_map;
  }


  private function parseCommitMessage($corpus, array $label_map) {
    $key_title = id(new DifferentialTitleField())->getFieldKeyForConduit();
    $key_summary = id(new DifferentialSummaryField())->getFieldKeyForConduit();

    $parser = id(new DifferentialCommitMessageParser())
      ->setLabelMap($label_map)
      ->setTitleKey($key_title)
      ->setSummaryKey($key_summary);

    $result = $parser->parseCorpus($corpus);

    foreach ($parser->getErrors() as $error) {
      $this->errors[] = $error;
    }

    return $result;
  }

}

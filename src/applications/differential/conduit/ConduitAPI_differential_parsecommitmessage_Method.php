<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_parsecommitmessage_Method
  extends ConduitAPIMethod {

  private $errors;

  public function getMethodDescription() {
    return "Parse commit messages for Differential fields.";
  }

  public function defineParamTypes() {
    return array(
      'corpus'  => 'required string',
      'partial' => 'optional bool',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $corpus = $request->getValue('corpus');
    $is_partial = $request->getValue('partial');

    $aux_fields = DifferentialFieldSelector::newSelector()
      ->getFieldSpecifications();

    foreach ($aux_fields as $key => $aux_field) {
      $aux_field->setUser($request->getUser());
      if (!$aux_field->shouldAppearOnCommitMessage()) {
        unset($aux_fields[$key]);
      }
    }

    $aux_fields = mpull($aux_fields, null, 'getCommitMessageKey');

    $this->errors = array();

    // Build a map from labels (like "Test Plan") to field keys
    // (like "testPlan").
    $label_map = $this->buildLabelMap($aux_fields);
    $field_map = $this->parseCommitMessage($corpus, $label_map);

    $fields = array();
    foreach ($field_map as $field_key => $field_value) {
      $field = $aux_fields[$field_key];
      try {
        $fields[$field_key] = $field->parseValueFromCommitMessage($field_value);
        $field->setValueFromParsedCommitMessage($fields[$field_key]);
      } catch (DifferentialFieldParseException $ex) {
        $field_label = $field->renderLabelForCommitMessage();
        $this->errors[] =
          "Error parsing field '{$field_label}': ".$ex->getMessage();
      }
    }

    if (!$is_partial) {
      foreach ($aux_fields as $field_key => $aux_field) {
        try {
          $aux_field->validateField();
        } catch (DifferentialFieldValidationException $ex) {
          $field_label = $aux_field->renderLabelForCommitMessage();
          $this->errors[] =
            "Invalid or missing field '{$field_label}': ".
            $ex->getMessage();
        }
      }
    }

    return array(
      'errors' => $this->errors,
      'fields' => $fields,
    );
  }

  private function buildLabelMap(array $aux_fields) {
    assert_instances_of($aux_fields, 'DifferentialFieldSpecification');
    $label_map = array();
    foreach ($aux_fields as $key => $aux_field) {
      $labels = $aux_field->getSupportedCommitMessageLabels();
      foreach ($labels as $label) {
        $normal_label = DifferentialCommitMessageParser::normalizeFieldLabel(
          $label);
        if (!empty($label_map[$normal_label])) {
          $previous = $label_map[$normal_label];
          throw new Exception(
            "Field label '{$label}' is parsed by two fields: '{$key}' and ".
            "'{$previous}'. Each label must be parsed by only one field.");
        }
        $label_map[$normal_label] = $key;
      }
    }
    return $label_map;
  }


  private function parseCommitMessage($corpus, array $label_map) {
    $parser = id(new DifferentialCommitMessageParser())
      ->setLabelMap($label_map)
      ->setTitleKey('title')
      ->setSummaryKey('summary');

    $result = $parser->parseCorpus($corpus);

    foreach ($parser->getErrors() as $error) {
      $this->errors[] = $error;
    }

    return $result;
  }


}

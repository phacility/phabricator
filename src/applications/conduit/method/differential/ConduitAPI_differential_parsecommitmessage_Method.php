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
      if (!$aux_field->shouldAppearOnCommitMessage()) {
        unset($aux_fields[$key]);
      }
      $aux_field->setUser($request->getUser());
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
        $normal_label = strtolower($label);
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

  private function buildLabelRegexp(array $label_map) {
    $field_labels = array_keys($label_map);
    foreach ($field_labels as $key => $label) {
      $field_labels[$key] = preg_quote($label, '/');
    }
    $field_labels = implode('|', $field_labels);

    $field_pattern = '/^(?P<field>'.$field_labels.'):(?P<text>.*)$/i';

    return $field_pattern;
  }

  private function parseCommitMessage($corpus, array $label_map) {
    $label_regexp = $this->buildLabelRegexp($label_map);

    // Note, deliberately not populating $seen with 'title' because it is
    // optional to include the 'Title:' label. We're doing a little special
    // casing to consume the first line as the title regardless of whether you
    // label it as such or not.
    $field = 'title';

    $seen = array();
    $lines = explode("\n", trim($corpus));
    $field_map = array();
    foreach ($lines as $key => $line) {
      $match = null;
      if (preg_match($label_regexp, $line, $match)) {
        $lines[$key] = trim($match['text']);
        $field = $label_map[strtolower($match['field'])];
        if (!empty($seen[$field])) {
          $this->errors[] = "Field '{$field}' occurs twice in commit message!";
        }
        $seen[$field] = true;
      }
      $field_map[$key] = $field;
    }

    $fields = array();
    foreach ($lines as $key => $line) {
      $fields[$field_map[$key]][] = $line;
    }

    // This is a piece of special-cased magic which allows you to omit the
    // field labels for "title" and "summary". If the user enters a large block
    // of text at the beginning of the commit message with an empty line in it,
    // treat everything before the blank line as "title" and everything after
    // as "summary".
    if (isset($fields['title']) && empty($fields['summary'])) {
      $lines = $fields['title'];
      for ($ii = 0; $ii < count($lines); $ii++) {
        if (strlen(trim($lines[$ii])) == 0) {
          break;
        }
      }
      if ($ii != count($lines)) {
        $fields['title'] = array_slice($lines, 0, $ii);
        $fields['summary'] = array_slice($lines, $ii);
      }
    }

    // Implode all the lines back into chunks of text.
    foreach ($fields as $name => $lines) {
      $data = rtrim(implode("\n", $lines));
      $data = ltrim($data, "\n");
      $fields[$name] = $data;
    }

    return $fields;
  }


}

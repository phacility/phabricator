<?php

final class ConduitColumnsParameterType
  extends ConduitParameterType {

  protected function getParameterValue(array $request, $key) {
    // We don't do any meaningful validation here because the transaction
    // itself validates everything and the input format is flexible.
    return parent::getParameterValue($request, $key);
  }

  protected function getParameterTypeName() {
    return 'columns';
  }

  protected function getParameterDefault() {
    return array();
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('Single column PHID.'),
      pht('List of column PHIDs.'),
      pht('List of position dictionaries.'),
      pht('List with a mixture of PHIDs and dictionaries.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      '"PHID-PCOL-1111"',
      '["PHID-PCOL-2222", "PHID-PCOL-3333"]',
      '[{"columnPHID": "PHID-PCOL-4444", "afterPHID": "PHID-TASK-5555"}]',
      '[{"columnPHID": "PHID-PCOL-4444", "beforePHID": "PHID-TASK-6666"}]',
    );
  }

}

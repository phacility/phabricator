<?php

final class ManiphestTaskListHTTPParameterType
  extends AphrontListHTTPParameterType {

  protected function getParameterValue(AphrontRequest $request, $key) {
    $type = new AphrontStringListHTTPParameterType();
    $list = $this->getValueWithType($type, $request, $key);

    return id(new ManiphestTaskPHIDResolver())
      ->setViewer($this->getViewer())
      ->resolvePHIDs($list);
  }

  protected function getParameterTypeName() {
    return 'list<task>';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('Comma-separated list of task PHIDs.'),
      pht('List of task PHIDs, as array.'),
      pht('Comma-separated list of task IDs.'),
      pht('List of task IDs, as array.'),
      pht('Comma-separated list of task monograms.'),
      pht('List of task monograms, as array.'),
      pht('Mixture of PHIDs, IDs and monograms.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v=PHID-TASK-1111',
      'v=PHID-TASK-1111,PHID-TASK-2222',
      'v[]=PHID-TASK-1111&v[]=PHID-TASK-2222',
      'v=123',
      'v=123,124',
      'v[]=123&v[]=124',
      'v=T123',
      'v=T123,T124',
      'v[]=T123&v[]=T124',
      'v=PHID-TASK-1111,123,T124',
      'v[]=PHID-TASK-1111&v[]=123&v[]=T124',
    );
  }

}

<?php

final class AphrontProjectListHTTPParameterType
  extends AphrontListHTTPParameterType {

  protected function getParameterValue(AphrontRequest $request, $key) {
    $type = new AphrontStringListHTTPParameterType();
    $list = $this->getValueWithType($type, $request, $key);

    return id(new PhabricatorProjectPHIDResolver())
      ->setViewer($this->getViewer())
      ->resolvePHIDs($list);
  }

  protected function getParameterTypeName() {
    return 'list<project>';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('Comma-separated list of project PHIDs.'),
      pht('List of project PHIDs, as array.'),
      pht('Comma-separated list of project hashtags.'),
      pht('List of project hashtags, as array.'),
      pht('Mixture of hashtags and PHIDs.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v=PHID-PROJ-1111',
      'v=PHID-PROJ-1111,PHID-PROJ-2222',
      'v=hashtag',
      'v=frontend,backend',
      'v[]=PHID-PROJ-1111&v[]=PHID-PROJ-2222',
      'v[]=frontend&v[]=backend',
      'v=PHID-PROJ-1111,frontend',
      'v[]=PHID-PROJ-1111&v[]=backend',
    );
  }

}

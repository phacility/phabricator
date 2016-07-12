<?php

final class AphrontUserListHTTPParameterType
  extends AphrontListHTTPParameterType {

  protected function getParameterValue(AphrontRequest $request, $key) {
    $type = new AphrontStringListHTTPParameterType();
    $list = $this->getValueWithType($type, $request, $key);

    return id(new PhabricatorUserPHIDResolver())
      ->setViewer($this->getViewer())
      ->resolvePHIDs($list);
  }

  protected function getParameterTypeName() {
    return 'list<user>';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('Comma-separated list of user PHIDs.'),
      pht('List of user PHIDs, as array.'),
      pht('Comma-separated list of usernames.'),
      pht('List of usernames, as array.'),
      pht('Mixture of usernames and PHIDs.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v=PHID-USER-1111',
      'v=PHID-USER-1111,PHID-USER-2222',
      'v=username',
      'v=alincoln,htaft',
      'v[]=PHID-USER-1111&v[]=PHID-USER-2222',
      'v[]=htaft&v[]=alincoln',
      'v=PHID-USER-1111,alincoln',
      'v[]=PHID-USER-1111&v[]=htaft',
    );
  }

}

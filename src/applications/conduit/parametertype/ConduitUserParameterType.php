<?php

final class ConduitUserParameterType
  extends ConduitParameterType {

  protected function getParameterValue(array $request, $key) {
    $value = parent::getParameterValue($request, $key);

    if ($value === null) {
      return null;
    }

    if (!is_string($value)) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected PHID or null, got something else.'));
    }

    $user_phids = id(new PhabricatorUserPHIDResolver())
      ->setViewer($this->getViewer())
      ->resolvePHIDs(array($value));

    return nonempty(head($user_phids), null);
  }

  protected function getParameterTypeName() {
    return 'phid|string|null';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('User PHID.'),
      pht('Username.'),
      pht('Literal null.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      '"PHID-USER-1111"',
      '"alincoln"',
      'null',
    );
  }

}

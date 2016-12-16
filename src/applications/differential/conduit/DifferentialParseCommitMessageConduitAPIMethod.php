<?php

final class DifferentialParseCommitMessageConduitAPIMethod
  extends DifferentialConduitAPIMethod {

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
    $viewer = $this->getViewer();

    $parser = DifferentialCommitMessageParser::newStandardParser($viewer);

    $is_partial = $request->getValue('partial');
    if ($is_partial) {
      $parser->setRaiseMissingFieldErrors(false);
    }

    $corpus = $request->getValue('corpus');
    $field_map = $parser->parseFields($corpus);

    $errors = $parser->getErrors();

    $revision_id_value = idx(
      $field_map,
      DifferentialRevisionIDCommitMessageField::FIELDKEY);
    $revision_id_valid_domain = PhabricatorEnv::getProductionURI('');

    return array(
      'errors' => $errors,
      'fields' => $field_map,
      'revisionIDFieldInfo' => array(
        'value' => $revision_id_value,
        'validDomain' => $revision_id_valid_domain,
      ),
    );
  }

}

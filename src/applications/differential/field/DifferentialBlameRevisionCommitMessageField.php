<?php

final class DifferentialBlameRevisionCommitMessageField
  extends DifferentialCommitMessageField {

  const FIELDKEY = 'blameRevision';

  public function getFieldName() {
    return pht('Blame Revision');
  }

  public function getFieldAliases() {
    return array(
      'Blame Rev',
    );
  }

  public function isFieldEnabled() {
    return $this->isCustomFieldEnabled('phabricator:blame-revision');
  }

}

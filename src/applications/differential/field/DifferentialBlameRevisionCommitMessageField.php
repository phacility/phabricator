<?php

final class DifferentialBlameRevisionCommitMessageField
  extends DifferentialCommitMessageCustomField {

  const FIELDKEY = 'blameRevision';

  public function getFieldName() {
    return pht('Blame Revision');
  }

  public function getFieldAliases() {
    return array(
      'Blame Rev',
    );
  }

  public function getCustomFieldKey() {
    return 'phabricator:blame-revision';
  }

}

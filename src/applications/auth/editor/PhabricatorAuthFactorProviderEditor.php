<?php

final class PhabricatorAuthFactorProviderEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('MFA Providers');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this MFA provider.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

}

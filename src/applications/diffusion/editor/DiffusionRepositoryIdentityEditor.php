<?php

final class DiffusionRepositoryIdentityEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorObjectsDescription() {
    return pht('Repository Identity');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this identity.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  protected function supportsSearch() {
    return true;
  }

  public function getEditorApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}

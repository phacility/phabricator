<?php

final class PhabricatorRepositoryURITransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_URI = 'diffusion.uri.uri';

  public function getApplicationName() {
    return 'repository';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRepositoryURIPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

}

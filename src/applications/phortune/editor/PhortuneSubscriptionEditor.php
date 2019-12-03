<?php

final class PhortuneSubscriptionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phortune Subscriptions');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this subscription.', $author);
  }

}

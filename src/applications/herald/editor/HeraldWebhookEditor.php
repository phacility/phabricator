<?php

final class HeraldWebhookEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Webhooks');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this webhook.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

}

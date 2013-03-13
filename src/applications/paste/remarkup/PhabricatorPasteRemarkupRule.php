<?php

/**
 * @group markup
 */
final class PhabricatorPasteRemarkupRule
  extends PhabricatorRemarkupRuleObject {

  protected function getObjectNamePrefix() {
    return 'P';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    return id(new PhabricatorPasteQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->needContent(true)
      ->execute();

  }

  protected function renderObjectEmbed($object, $handle, $options) {
    $embed_paste = id(new PasteEmbedView())
      ->setPaste($object)
      ->setHandle($handle);

    return $embed_paste->render();

  }
}

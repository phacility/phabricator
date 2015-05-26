<?php

final class PhabricatorPasteRemarkupRule extends PhabricatorObjectRemarkupRule {

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

  protected function renderObjectEmbed(
    $object,
    PhabricatorObjectHandle $handle,
    $options) {

    $embed_paste = id(new PasteEmbedView())
      ->setPaste($object)
      ->setHandle($handle);

    if (strlen($options)) {
      $parser = new PhutilSimpleOptions();
      $opts = $parser->parse(substr($options, 1));

      foreach ($opts as $key => $value) {
        if ($key == 'lines') {
          $embed_paste->setLines(preg_replace('/[^0-9]/', '', $value));
        } else if ($key == 'highlight') {
          $highlights = preg_split('/,|&/', preg_replace('/\s+/', '', $value));

          $to_highlight = array();
          foreach ($highlights as $highlight) {
            $highlight = explode('-', $highlight);

            if (!empty($highlight)) {
              sort($highlight);
              $to_highlight = array_merge(
                $to_highlight,
                range(head($highlight), last($highlight)));
            }
          }

          $embed_paste->setHighlights(array_unique($to_highlight));
        }
      }

    }

    return $embed_paste;
  }

}

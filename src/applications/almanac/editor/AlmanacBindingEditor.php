<?php

final class AlmanacBindingEditor
  extends AlmanacEditor {

  public function getEditorObjectsDescription() {
    return pht('Almanac Binding');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this binding.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  protected function supportsSearch() {
    return true;
  }

}

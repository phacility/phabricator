<?php

final class PhabricatorRepositoryURITransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_URI = 'diffusion.uri.uri';
  const TYPE_IO = 'diffusion.uri.io';
  const TYPE_DISPLAY = 'diffusion.uri.display';

  public function getApplicationName() {
    return 'repository';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRepositoryURIPHIDType::TYPECONST;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_URI:
        return pht(
          '%s changed this URI from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
      case self::TYPE_IO:
        $map = PhabricatorRepositoryURI::getIOTypeMap();
        $old_label = idx(idx($map, $old, array()), 'label', $old);
        $new_label = idx(idx($map, $new, array()), 'label', $new);

        return pht(
          '%s changed the display type for this URI from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old_label,
          $new_label);
      case self::TYPE_DISPLAY:
        $map = PhabricatorRepositoryURI::getDisplayTypeMap();
        $old_label = idx(idx($map, $old, array()), 'label', $old);
        $new_label = idx(idx($map, $new, array()), 'label', $new);

        return pht(
          '%s changed the display type for this URI from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old_label,
          $new_label);

    }

    return parent::getTitle();
  }

}

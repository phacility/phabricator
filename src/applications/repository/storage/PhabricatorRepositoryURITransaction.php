<?php

final class PhabricatorRepositoryURITransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_REPOSITORY = 'diffusion.uri.repository';
  const TYPE_URI = 'diffusion.uri.uri';
  const TYPE_IO = 'diffusion.uri.io';
  const TYPE_DISPLAY = 'diffusion.uri.display';
  const TYPE_CREDENTIAL = 'diffusion.uri.credential';
  const TYPE_DISABLE = 'diffusion.uri.disable';

  public function getApplicationName() {
    return 'repository';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRepositoryURIPHIDType::TYPECONST;
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_CREDENTIAL:
        if ($old) {
          $phids[] = $old;
        }
        if ($new) {
          $phids[] = $new;
        }
        break;
    }

    return $phids;
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
      case self::TYPE_DISABLE:
        if ($new) {
          return pht(
            '%s disabled this URI.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s enabled this URI.',
            $this->renderHandleLink($author_phid));
        }
      case self::TYPE_CREDENTIAL:
        if ($old && $new) {
          return pht(
            '%s changed the credential for this URI from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        } else if ($old) {
          return pht(
            '%s removed %s as the credential for this URI.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old));
        } else if ($new) {
          return pht(
            '%s set the credential for this URI to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($new));
        }

    }

    return parent::getTitle();
  }

}

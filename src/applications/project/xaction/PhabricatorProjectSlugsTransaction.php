<?php

final class PhabricatorProjectSlugsTransaction
  extends PhabricatorProjectTransactionType {

  const TRANSACTIONTYPE = 'project:slugs';

  public function generateOldValue($object) {
    $slugs = $object->getSlugs();
    $slugs = mpull($slugs, 'getSlug', 'getSlug');
    unset($slugs[$object->getPrimarySlug()]);
    return array_keys($slugs);
  }

  public function generateNewValue($object, $value) {
    return $this->getEditor()->normalizeSlugs($value);
  }

  public function applyInternalEffects($object, $value) {
    return;
  }

  public function applyExternalEffects($object, $value) {
   $old = $this->getOldValue();
   $new = $value;
   $add = array_diff($new, $old);
   $rem = array_diff($old, $new);

   foreach ($add as $slug) {
     $this->getEditor()->addSlug($object, $slug, true);
   }

   $this->getEditor()->removeSlugs($object, $rem);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    $add = $this->renderHashtags($add);
    $rem = $this->renderHashtags($rem);

    if ($add && $rem) {
      return pht(
        '%s changed project hashtag(s), added %d: %s; removed %d: %s.',
        $this->renderAuthor(),
        count($add),
        $this->renderValueList($add),
        count($rem),
        $this->renderValueList($rem));
    } else if ($add) {
      return pht(
        '%s added %d project hashtag(s): %s.',
        $this->renderAuthor(),
        count($add),
        $this->renderValueList($add));
    } else if ($rem) {
        return pht(
          '%s removed %d project hashtag(s): %s.',
          $this->renderAuthor(),
          count($rem),
          $this->renderValueList($rem));
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    $add = $this->renderHashtags($add);
    $rem = $this->renderHashtags($rem);

    if ($add && $rem) {
      return pht(
        '%s changed %s hashtag(s), added %d: %s; removed %d: %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        count($add),
        $this->renderValueList($add),
        count($rem),
        $this->renderValueList($rem));
    } else if ($add) {
      return pht(
        '%s added %d %s hashtag(s): %s.',
        $this->renderAuthor(),
        count($add),
        $this->renderObject(),
        $this->renderValueList($add));
    } else if ($rem) {
      return pht(
        '%s removed %d %s hashtag(s): %s.',
        $this->renderAuthor(),
        count($rem),
        $this->renderObject(),
        $this->renderValueList($rem));
    }
  }

  public function getIcon() {
    return 'fa-tag';
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if (!$xactions) {
      return $errors;
    }

    $slug_xaction = last($xactions);

    $new = $slug_xaction->getNewValue();

    $invalid = array();
    foreach ($new as $slug) {
      if (!PhabricatorSlug::isValidProjectSlug($slug)) {
        $invalid[] = $slug;
      }
    }

    if ($invalid) {
      $errors[] = $this->newInvalidError(
        pht(
          'Hashtags must contain at least one letter or number. %s '.
          'project hashtag(s) are invalid: %s.',
          phutil_count($invalid),
          implode(', ', $invalid)));

      return $errors;
    }

    $new = $this->getEditor()->normalizeSlugs($new);

    if ($new) {
      $slugs_used_already = id(new PhabricatorProjectSlug())
        ->loadAllWhere('slug IN (%Ls)', $new);
    } else {
      // The project doesn't have any extra slugs.
      $slugs_used_already = array();
    }

    $slugs_used_already = mgroup($slugs_used_already, 'getProjectPHID');
    foreach ($slugs_used_already as $project_phid => $used_slugs) {
      if ($project_phid == $object->getPHID()) {
        continue;
      }

      $used_slug_strs = mpull($used_slugs, 'getSlug');

      $errors[] = $this->newInvalidError(
        pht(
          '%s project hashtag(s) are already used by other projects: %s.',
          phutil_count($used_slug_strs),
          implode(', ', $used_slug_strs)));
    }

    return $errors;
  }

  private function renderHashtags(array $tags) {
    $result = array();
    foreach ($tags as $tag) {
      $result[] = '#'.$tag;
    }
    return $result;
  }

}

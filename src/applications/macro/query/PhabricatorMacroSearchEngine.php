<?php

final class PhabricatorMacroSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Macros');
  }

  public function getApplicationClassName() {
    return 'PhabricatorMacroApplication';
  }

  public function newQuery() {
    return id(new PhabricatorMacroQuery())
      ->needFiles(true);
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Status'))
        ->setKey('status')
        ->setOptions(PhabricatorMacroQuery::getStatusOptions()),
      id(new PhabricatorSearchUsersField())
        ->setLabel(pht('Authors'))
        ->setKey('authorPHIDs')
        ->setAliases(array('author', 'authors')),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('nameLike'),
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Exact Names'))
        ->setKey('names'),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Marked with Flag'))
        ->setKey('flagColor')
        ->setDefault('-1')
        ->setOptions(PhabricatorMacroQuery::getFlagColorsOptions()),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created After'))
        ->setKey('createdStart'),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created Before'))
        ->setKey('createdEnd'),
    );
  }

  protected function getDefaultFieldOrder() {
    return array(
      '...',
      'createdStart',
      'createdEnd',
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['authorPHIDs']) {
      $query->withAuthorPHIDs($map['authorPHIDs']);
    }

    if ($map['status']) {
      $query->withStatus($map['status']);
    }

    if ($map['names']) {
      $query->withNames($map['names']);
    }

    if (strlen($map['nameLike'])) {
      $query->withNameLike($map['nameLike']);
    }

    if ($map['createdStart']) {
      $query->withDateCreatedAfter($map['createdStart']);
    }

    if ($map['createdEnd']) {
      $query->withDateCreatedBefore($map['createdEnd']);
    }

    if ($map['flagColor'] !== null) {
      $query->withFlagColor($map['flagColor']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/macro/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'active'  => pht('Active'),
      'all'     => pht('All'),
    );

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored');
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query;
      case 'all':
        return $query->setParameter(
          'status',
          PhabricatorMacroQuery::STATUS_ANY);
      case 'authored':
        return $query->setParameter(
          'authorPHIDs',
          array($this->requireViewer()->getPHID()));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $macros,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($macros, 'PhabricatorFileImageMacro');
    $viewer = $this->requireViewer();
    $handles = $viewer->loadHandles(mpull($macros, 'getAuthorPHID'));

    $xform = PhabricatorFileTransform::getTransformByKey(
      PhabricatorFileThumbnailTransform::TRANSFORM_PINBOARD);

    $pinboard = new PHUIPinboardView();
    foreach ($macros as $macro) {
      $file = $macro->getFile();

      $item = id(new PHUIPinboardItemView())
        ->setUser($viewer)
        ->setObject($macro);

      if ($file) {
        $item->setImageURI($file->getURIForTransform($xform));
        list($x, $y) = $xform->getTransformedDimensions($file);
        $item->setImageSize($x, $y);
      }

      if ($macro->getDateCreated()) {
        $datetime = phabricator_date($macro->getDateCreated(), $viewer);
        $item->appendChild(
          phutil_tag(
            'div',
            array(),
            pht('Created on %s', $datetime)));
      } else {
        // Very old macros don't have a creation date. Rendering something
        // keeps all the pins at the same height and avoids flow issues.
        $item->appendChild(
          phutil_tag(
            'div',
            array(),
            pht('Created in ages long past')));
      }

      if ($macro->getAuthorPHID()) {
        $author_handle = $handles[$macro->getAuthorPHID()];
        $item->appendChild(
          pht('Created by %s', $author_handle->renderLink()));
      }

      $item->setURI($this->getApplicationURI('/view/'.$macro->getID().'/'));
      $item->setDisabled($macro->getisDisabled());
      $item->setHeader($macro->getName());

      $pinboard->addItem($item);
    }

    return $pinboard;
  }

}

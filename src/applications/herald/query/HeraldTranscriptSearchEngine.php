<?php

final class HeraldTranscriptSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $object_monograms = $request->getStrList('objectMonograms');
    $saved->setParameter('objectMonograms', $object_monograms);

    $ids = $request->getStrList('ids');
    foreach ($ids as $key => $id) {
      if (!$id || !is_numeric($id)) {
        unset($ids[$key]);
      } else {
        $ids[$key] = $id;
      }
    }
    $saved->setParameter('ids', $ids);

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new HeraldTranscriptQuery());

    $object_monograms = $saved->getParameter('objectMonograms');
    if ($object_monograms) {
      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($this->requireViewer())
        ->withNames($object_monograms)
        ->execute();
      $query->withObjectPHIDs(mpull($objects, 'getPHID'));
    }

    $ids = $saved->getParameter('ids');
    if ($ids) {
      $query->withIDs($ids);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $object_monograms = $saved->getParameter('objectMonograms', array());
    $ids = $saved->getParameter('ids', array());

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('objectMonograms')
          ->setLabel(pht('Object Monograms'))
          ->setValue(implode(', ', $object_monograms)))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('ids')
          ->setLabel(pht('Transcript IDs'))
          ->setValue(implode(', ', $ids)));
  }

  protected function getURI($path) {
    return '/herald/transcript/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array();

    $names['all'] = pht('All');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    $viewer_phid = $this->requireViewer()->getPHID();

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}

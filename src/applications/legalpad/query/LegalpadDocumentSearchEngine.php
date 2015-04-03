<?php

final class LegalpadDocumentSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Legalpad Documents');
  }

  public function getApplicationClassName() {
    return 'PhabricatorLegalpadApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter(
      'creatorPHIDs',
      $this->readUsersFromRequest($request, 'creators'));

    $saved->setParameter(
      'contributorPHIDs',
      $this->readUsersFromRequest($request, 'contributors'));

    $saved->setParameter(
      'withViewerSignature',
      $request->getBool('withViewerSignature'));

    $saved->setParameter('createdStart', $request->getStr('createdStart'));
    $saved->setParameter('createdEnd', $request->getStr('createdEnd'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new LegalpadDocumentQuery())
      ->needViewerSignatures(true);

    $creator_phids = $saved->getParameter('creatorPHIDs', array());
    if ($creator_phids) {
      $query->withCreatorPHIDs($creator_phids);
    }

    $contributor_phids = $saved->getParameter('contributorPHIDs', array());
    if ($contributor_phids) {
      $query->withContributorPHIDs($contributor_phids);
    }

    if ($saved->getParameter('withViewerSignature')) {
      $viewer_phid = $this->requireViewer()->getPHID();
      if ($viewer_phid) {
        $query->withSignerPHIDs(array($viewer_phid));
      }
    }

    $start = $this->parseDateTime($saved->getParameter('createdStart'));
    $end = $this->parseDateTime($saved->getParameter('createdEnd'));

    if ($start) {
      $query->withDateCreatedAfter($start);
    }

    if ($end) {
      $query->withDateCreatedBefore($end);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $creator_phids = $saved_query->getParameter('creatorPHIDs', array());
    $contributor_phids = $saved_query->getParameter(
      'contributorPHIDs', array());

    $viewer_signature = $saved_query->getParameter('withViewerSignature');
    if (!$this->requireViewer()->getPHID()) {
      $viewer_signature = false;
    }

    $form
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'withViewerSignature',
            1,
            pht('Show only documents I have signed.'),
            $viewer_signature)
          ->setDisabled(!$this->requireViewer()->getPHID()))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('creators')
          ->setLabel(pht('Creators'))
          ->setValue($creator_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('contributors')
          ->setLabel(pht('Contributors'))
          ->setValue($contributor_phids));

    $this->buildDateRange(
      $form,
      $saved_query,
      'createdStart',
      pht('Created After'),
      'createdEnd',
      pht('Created Before'));

  }

  protected function getURI($path) {
    return '/legalpad/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['signed'] = pht('Signed Documents');
    }

    $names['all'] = pht('All Documents');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'signed':
        return $query
          ->setParameter('withViewerSignature', true);
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $documents,
    PhabricatorSavedQuery $query) {
    return array();
  }

  protected function renderResultList(
    array $documents,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($documents, 'LegalpadDocument');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($documents as $document) {
      $last_updated = phabricator_date($document->getDateModified(), $viewer);

      $title = $document->getTitle();

      $item = id(new PHUIObjectItemView())
        ->setObjectName($document->getMonogram())
        ->setHeader($title)
        ->setHref('/'.$document->getMonogram())
        ->setObject($document);

      $no_signatures = LegalpadDocument::SIGNATURE_TYPE_NONE;
      if ($document->getSignatureType() == $no_signatures) {
        $item->addIcon('none', pht('Not Signable'));
      } else {

        $type_name = $document->getSignatureTypeName();
        $type_icon = $document->getSignatureTypeIcon();
        $item->addIcon($type_icon, $type_name);

        if ($viewer->getPHID()) {
          $signature = $document->getUserSignature($viewer->getPHID());
        } else {
          $signature = null;
        }

        if ($signature) {
          $item->addAttribute(
            array(
              id(new PHUIIconView())->setIconFont('fa-check-square-o', 'green'),
              ' ',
              pht(
                'Signed on %s',
                phabricator_date($signature->getDateCreated(), $viewer)),
            ));
        } else {
          $item->addAttribute(
            array(
              id(new PHUIIconView())->setIconFont('fa-square-o', 'grey'),
              ' ',
              pht('Not Signed'),
            ));
        }
      }

      $item->addIcon(
        'fa-pencil grey',
        pht('Version %d (%s)', $document->getVersions(), $last_updated));

      $list->addItem($item);
    }

    return $list;
  }

}

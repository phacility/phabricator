<?php

final class LegalpadDocumentSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Legalpad Documents');
  }

  public function getApplicationClassName() {
    return 'PhabricatorLegalpadApplication';
  }

  public function newQuery() {
    return id(new LegalpadDocumentQuery())
      ->needViewerSignatures(true);
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Signed By'))
        ->setKey('signerPHIDs')
        ->setAliases(array('signer', 'signers', 'signerPHID'))
        ->setDescription(
          pht('Search for documents signed by given users.')),
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Creators'))
        ->setKey('creatorPHIDs')
        ->setAliases(array('creator', 'creators', 'creatorPHID'))
        ->setDescription(
          pht('Search for documents with given creators.')),
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Contributors'))
        ->setKey('contributorPHIDs')
        ->setAliases(array('contributor', 'contributors', 'contributorPHID'))
        ->setDescription(
          pht('Search for documents with given contributors.')),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created After'))
        ->setKey('createdStart'),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created Before'))
        ->setKey('createdEnd'),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['signerPHIDs']) {
      $query->withSignerPHIDs($map['signerPHIDs']);
    }

    if ($map['contributorPHIDs']) {
      $query->withContributorPHIDs($map['contributorPHIDs']);
    }

    if ($map['creatorPHIDs']) {
      $query->withCreatorPHIDs($map['creatorPHIDs']);
    }

    if ($map['createdStart']) {
      $query->withDateCreatedAfter($map['createdStart']);
    }

    if ($map['createdEnd']) {
      $query->withDateCreatedAfter($map['createdStart']);
    }

    return $query;
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

    $viewer = $this->requireViewer();

    switch ($query_key) {
      case 'signed':
        return $query->setParameter('signerPHIDs', array($viewer->getPHID()));
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
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
              id(new PHUIIconView())->setIcon('fa-check-square-o', 'green'),
              ' ',
              pht(
                'Signed on %s',
                phabricator_date($signature->getDateCreated(), $viewer)),
            ));
        } else {
          $item->addAttribute(
            array(
              id(new PHUIIconView())->setIcon('fa-square-o', 'grey'),
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

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No documents found.'));

    return $result;
  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Create a Document'))
      ->setHref('/legalpad/edit/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Create documents and track signatures.'))
      ->addAction($create_button);

      return $view;
  }

}

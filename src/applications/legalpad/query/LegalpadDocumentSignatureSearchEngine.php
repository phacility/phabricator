<?php

final class LegalpadDocumentSignatureSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $document;

  public function getResultTypeDescription() {
    return pht('Legalpad Signatures');
  }

  public function getApplicationClassName() {
    return 'PhabricatorApplicationLegalpad';
  }

  public function setDocument(LegalpadDocument $document) {
    $this->document = $document;
    return $this;
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'signerPHIDs',
      $this->readUsersFromRequest($request, 'signers'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new LegalpadDocumentSignatureQuery());

    $signer_phids = $saved->getParameter('signerPHIDs', array());
    if ($signer_phids) {
      $query->withSignerPHIDs($signer_phids);
    }

    if ($this->document) {
      $query->withDocumentPHIDs(array($this->document->getPHID()));
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $signer_phids = $saved_query->getParameter('signerPHIDs', array());

    $phids = array_merge($signer_phids);
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireViewer())
      ->withPHIDs($phids)
      ->execute();

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('signers')
          ->setLabel(pht('Signers'))
          ->setValue(array_select_keys($handles, $signer_phids)));
  }

  protected function getURI($path) {
    if ($this->document) {
      return '/legalpad/signatures/'.$this->document->getID().'/'.$path;
    } else {
      throw new Exception(
        pht(
          'Searching for signatures outside of a document context is not '.
          'currently supported.'));
    }
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Signatures'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $documents,
    PhabricatorSavedQuery $query) {
    return mpull($documents, 'getSignerPHID');
  }

  protected function renderResultList(
    array $signatures,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($signatures, 'LegalpadDocumentSignature');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);

    foreach ($signatures as $signature) {
      $created = phabricator_date($signature->getDateCreated(), $viewer);

      $data = $signature->getSignatureData();

      $sig_data = phutil_tag(
        'div',
        array(),
        array(
          phutil_tag(
            'div',
            array(),
            phutil_tag(
              'a',
              array(
                'href' => 'mailto:'.$data['email'],
              ),
              $data['email'])),
          ));

      $item = id(new PHUIObjectItemView())
        ->setObject($signature)
        ->setHeader($data['name'])
        ->setSubhead($sig_data)
        ->addIcon('none', pht('Signed %s', $created));

      $good_sig = true;
      if (!$signature->isVerified()) {
        $item->addFootIcon('disable', 'Unverified Email');
        $good_sig = false;
      }

      $document = $signature->getDocument();
      if ($signature->getDocumentVersion() != $document->getVersions()) {
        $item->addFootIcon('delete', 'Stale Signature');
        $good_sig = false;
      }

      if ($good_sig) {
        $item->setBarColor('green');
      }

      $list->addItem($item);
    }

    return $list;
  }

}

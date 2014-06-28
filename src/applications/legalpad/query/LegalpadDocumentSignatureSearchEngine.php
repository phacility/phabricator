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

    Javelin::initBehavior('phabricator-tooltips');

    $sig_good = $this->renderIcon(
      'fa-check',
      null,
      pht('Verified, Current'));

    $sig_old = $this->renderIcon(
      'fa-clock-o',
      'orange',
      pht('Signed Older Version'));

    $sig_unverified = $this->renderIcon(
      'fa-envelope',
      'red',
      pht('Unverified Email'));

    id(new PHUIIconView())
      ->setIconFont('fa-envelope', 'red')
      ->addSigil('has-tooltip')
      ->setMetadata(array('tip' => pht('Unverified Email')));

    $rows = array();
    foreach ($signatures as $signature) {
      $data = $signature->getSignatureData();
      $name = idx($data, 'name');
      $email = idx($data, 'email');

      $document = $signature->getDocument();

      if (!$signature->isVerified()) {
        $sig_icon = $sig_unverified;
      } else if ($signature->getDocumentVersion() != $document->getVersions()) {
        $sig_icon = $sig_old;
      } else {
        $sig_icon = $sig_good;
      }

      $rows[] = array(
        $sig_icon,
        $handles[$signature->getSignerPHID()]->renderLink(),
        $name,
        phutil_tag(
          'a',
          array(
            'href' => 'mailto:'.$email,
          ),
          $email),
        phabricator_datetime($signature->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          '',
          pht('Account'),
          pht('Name'),
          pht('Email'),
          pht('Signed'),
        ))
      ->setColumnClasses(
        array(
          '',
          '',
          '',
          'wide',
          'right',
        ));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Signatures'))
      ->appendChild($table);

    return $box;
  }

  private function renderIcon($icon, $color, $title) {
    Javelin::initBehavior('phabricator-tooltips');

    return array(
      id(new PHUIIconView())
        ->setIconFont($icon, $color)
        ->addSigil('has-tooltip')
        ->setMetadata(array('tip' => $title)),
      javelin_tag(
        'span',
        array(
          'aural' => true,
        ),
        $title),
    );
  }

}

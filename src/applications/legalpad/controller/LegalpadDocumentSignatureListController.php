<?php

final class LegalpadDocumentSignatureListController extends LegalpadController {

  private $documentId;

  public function willProcessRequest(array $data) {
    $this->documentId = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $document = id(new LegalpadDocumentQuery())
      ->setViewer($user)
      ->withIDs(array($this->documentId))
      ->executeOne();

    if (!$document) {
      return new Aphront404Response();
    }

    $title = pht('Signatures for %s', $document->getMonogram());

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);
    $signatures = id(new LegalpadDocumentSignatureQuery())
      ->setViewer($user)
      ->withDocumentPHIDs(array($document->getPHID()))
      ->executeWithCursorPager($pager);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNav());
    $crumbs->addTextCrumb(
      $document->getMonogram(),
      $this->getApplicationURI('view/'.$document->getID()));

    $crumbs->addTextCrumb(
      pht('Signatures'));
    $list = $this->renderResultsList($document, $signatures);
    $list->setPager($pager);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $list,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function renderResultsList(
    LegalpadDocument $document,
    array $signatures) {
    assert_instances_of($signatures, 'LegalpadDocumentSignature');

    $user = $this->getRequest()->getUser();

    $list = new PHUIObjectItemListView();
    $list->setUser($user);

    foreach ($signatures as $signature) {
      $created = phabricator_date($signature->getDateCreated(), $user);

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
          phutil_tag(
            'div',
            array(),
            $data['address_1']),
          phutil_tag(
            'div',
            array(),
            $data['address_2']),
          phutil_tag(
            'div',
            array(),
            $data['phone'])
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

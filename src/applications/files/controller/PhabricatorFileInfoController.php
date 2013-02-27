<?php

final class PhabricatorFileInfoController extends PhabricatorFileController {

  private $phid;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $file = id(new PhabricatorFileQuery())
      ->setViewer($user)
      ->withPHIDs(array($this->phid))
      ->executeOne();

    if (!$file) {
      return new Aphront404Response();
    }

    $this->loadHandles(array($file->getAuthorPHID()));

    $phid = $file->getPHID();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName('F'.$file->getID())
        ->setHref($this->getApplicationURI("/info/{$phid}/")));

    $header = id(new PhabricatorHeaderView())
      ->setHeader($file->getName());

    $ttl = $file->getTTL();
    if ($ttl !== null) {
      $ttl_tag = id(new PhabricatorTagView())
        ->setType(PhabricatorTagView::TYPE_OBJECT)
        ->setName(pht("Temporary"));
      $header->addTag($ttl_tag);
    }

    $actions = $this->buildActionView($file);
    $properties = $this->buildPropertyView($file);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
      ),
      array(
        'title' => $file->getName(),
        'device'  => true,
      ));
  }

  private function buildActionView(PhabricatorFile $file) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $id = $file->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObject($file);

    if ($file->isViewableInBrowser()) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View File'))
          ->setIcon('preview')
          ->setHref($file->getViewURI()));
    } else {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setUser($user)
          ->setRenderAsForm(true)
          ->setDownload(true)
          ->setName(pht('Download File'))
          ->setIcon('download')
          ->setHref($file->getViewURI()));
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Delete File'))
        ->setIcon('delete')
        ->setHref($this->getApplicationURI("/delete/{$id}/"))
        ->setWorkflow(true));

    return $view;
  }

  private function buildPropertyView(PhabricatorFile $file) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $view = id(new PhabricatorPropertyListView());

    if ($file->getAuthorPHID()) {
      $view->addProperty(
        pht('Author'),
        $this->getHandle($file->getAuthorPHID())->renderLink());
    }

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($file->getDateCreated(), $user));

    $view->addProperty(
      pht('Size'),
      phabricator_format_bytes($file->getByteSize()));

    $view->addSectionHeader(pht('Technical Details'));

    $view->addProperty(
      pht('Mime Type'),
      $file->getMimeType());

    $view->addProperty(
      pht('Engine'),
      $file->getStorageEngine());

    $view->addProperty(
      pht('Format'),
      $file->getStorageFormat());

    $view->addProperty(
      pht('Handle'),
      $file->getStorageHandle());

    $metadata = $file->getMetadata();
    if (!empty($metadata)) {
      $view->addSectionHeader(pht('Metadata'));

      foreach ($metadata as $key => $value) {
        $view->addProperty(
          PhabricatorFile::getMetadataName($key),
          $value);
      }
    }

    if ($file->isViewableImage()) {

      $image = phutil_tag(
        'img',
        array(
          'src' => $file->getViewURI(),
          'class' => 'phabricator-property-list-image',
        ));

      $linked_image = phutil_tag(
        'a',
        array(
          'href' => $file->getViewURI(),
        ),
        $image);

      $view->addImageContent($linked_image);
    }

    return $view;
  }

}

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
      ->setObjectName('F'.$file->getID())
      ->setHeader($file->getName());

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
      phutil_escape_html($file->getMimeType()));

    $view->addProperty(
      pht('Engine'),
      phutil_escape_html($file->getStorageEngine()));

    $view->addProperty(
      pht('Format'),
      phutil_escape_html($file->getStorageFormat()));

    $view->addProperty(
      pht('Handle'),
      phutil_escape_html($file->getStorageHandle()));

    $metadata = $file->getMetadata();
    if (!empty($metadata)) {
      $view->addSectionHeader(pht('Metadata'));

      foreach ($metadata as $key => $value) {
        $view->addProperty(
          PhabricatorFile::getMetadataName($key),
          phutil_escape_html($value));
      }
    }

    if ($file->isViewableImage()) {

      // TODO: Clean this up after Pholio (dark backgrounds, standardization,
      // etc.)

      $image = phutil_render_tag(
        'img',
        array(
          'src' => $file->getViewURI(),
          'class' => 'phabricator-property-list-image',
        ));

      $linked_image = phutil_render_tag(
        'a',
        array(
          'href' => $file->getViewURI(),
        ),
        $image);

      $view->addTextContent($linked_image);
    }

    return $view;
  }

}

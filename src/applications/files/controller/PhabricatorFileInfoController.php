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

    $phid = $file->getPHID();
    $xactions = id(new PhabricatorFileTransactionQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($phid))
      ->execute();

    $handle_phids = array_merge(
      array($file->getAuthorPHID()),
      $file->getObjectPHIDs());

    $this->loadHandles($handle_phids);
    $header = id(new PHUIHeaderView())
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
    $timeline = $this->buildTransactionView($file, $xactions);
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setActionList($actions);
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName('F'.$file->getID())
        ->setHref($this->getApplicationURI("/info/{$phid}/")));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setActionList($actions)
      ->setPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $timeline
      ),
      array(
        'title' => $file->getName(),
        'device'  => true,
        'pageObjects' => array($file->getPHID()),
      ));
  }

  private function buildTransactionView(
    PhabricatorFile $file,
    array $xactions) {

    $user = $this->getRequest()->getUser();
    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($user)
      ->setObjectPHID($file->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $add_comment_header = id(new PHUIHeaderView())
      ->setHeader(
        $is_serious
          ? pht('Add Comment')
          : pht('Question File Integrity'));

    $submit_button_name = $is_serious
      ? pht('Add Comment')
      : pht('Debate the Bits');

    $draft = PhabricatorDraft::newFromUserAndKey($user, $file->getPHID());

    $add_comment_form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($user)
      ->setObjectPHID($file->getPHID())
      ->setDraft($draft)
      ->setAction($this->getApplicationURI('/comment/'.$file->getID().'/'))
      ->setSubmitButtonName($submit_button_name);

    $comment_box = id(new PHUIObjectBoxView())
      ->setFlush(true)
      ->setHeader($add_comment_header)
      ->appendChild($add_comment_form);

    return array(
      $timeline,
      $comment_box);
  }

  private function buildActionView(PhabricatorFile $file) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $id = $file->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObjectURI($this->getRequest()->getRequestURI())
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

    $phids = $file->getObjectPHIDs();
    if ($phids) {
      $view->addSectionHeader(pht('Attached'));
      $view->addProperty(
        pht('Attached To'),
        $this->renderHandlesForPHIDs($phids));
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
    } else if ($file->isAudio()) {
      $audio = phutil_tag(
        'audio',
        array(
          'controls' => 'controls',
          'class' => 'phabricator-property-list-audio',
        ),
        phutil_tag(
          'source',
          array(
            'src' => $file->getViewURI(),
            'type' => $file->getMimeType(),
          )));
      $view->addImageContent($audio);
    }

    return $view;
  }

}

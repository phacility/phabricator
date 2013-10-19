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
    $timeline = $this->buildTransactionView($file, $xactions);
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setActionList($actions);
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName('F'.$file->getID())
        ->setHref($this->getApplicationURI("/info/{$phid}/")));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header);

    $this->buildPropertyViews($object_box, $file, $actions);

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

  private function buildPropertyViews(
    PHUIObjectBoxView $box,
    PhabricatorFile $file,
    PhabricatorActionListView $actions) {
    $request = $this->getRequest();
    $user = $request->getUser();


    $properties = id(new PHUIPropertyListView());
    $properties->setActionList($actions);
    $box->addPropertyList($properties, pht('Details'));

    if ($file->getAuthorPHID()) {
      $properties->addProperty(
        pht('Author'),
        $this->getHandle($file->getAuthorPHID())->renderLink());
    }

    $properties->addProperty(
      pht('Created'),
      phabricator_datetime($file->getDateCreated(), $user));


    $finfo = id(new PHUIPropertyListView());
    $box->addPropertyList($finfo, pht('File Info'));

    $finfo->addProperty(
      pht('Size'),
      phabricator_format_bytes($file->getByteSize()));

    $finfo->addProperty(
      pht('Mime Type'),
      $file->getMimeType());

    $width = $file->getImageWidth();
    if ($width) {
      $finfo->addProperty(
        pht('Width'),
        pht('%s px', new PhutilNumber($width)));
    }

    $height = $file->getImageHeight();
    if ($height) {
      $finfo->addProperty(
        pht('Height'),
        pht('%s px', new PhutilNumber($height)));
    }


    $storage_properties = new PHUIPropertyListView();
    $box->addPropertyList($storage_properties, pht('Storage'));

    $storage_properties->addProperty(
      pht('Engine'),
      $file->getStorageEngine());

    $storage_properties->addProperty(
      pht('Format'),
      $file->getStorageFormat());

    $storage_properties->addProperty(
      pht('Handle'),
      $file->getStorageHandle());


    $phids = $file->getObjectPHIDs();
    if ($phids) {
      $attached = new PHUIPropertyListView();
      $box->addPropertyList($attached, pht('Attached'));

      $attached->addProperty(
        pht('Attached To'),
        $this->renderHandlesForPHIDs($phids));
    }


    if ($file->isViewableImage()) {
      $image = phutil_tag(
        'img',
        array(
          'src' => $file->getViewURI(),
          'class' => 'phui-property-list-image',
        ));

      $linked_image = phutil_tag(
        'a',
        array(
          'href' => $file->getViewURI(),
        ),
        $image);

      $media = id(new PHUIPropertyListView())
        ->addImageContent($linked_image);

      $box->addPropertyList($media);
    } else if ($file->isAudio()) {
      $audio = phutil_tag(
        'audio',
        array(
          'controls' => 'controls',
          'class' => 'phui-property-list-audio',
        ),
        phutil_tag(
          'source',
          array(
            'src' => $file->getViewURI(),
            'type' => $file->getMimeType(),
          )));
      $media = id(new PHUIPropertyListView())
        ->addImageContent($audio);

      $box->addPropertyList($media);
    }
  }

}

<?php

final class PhabricatorFileInfoController extends PhabricatorFileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $phid = $request->getURIData('phid');

    if ($phid) {
      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($phid))
        ->executeOne();

      if (!$file) {
        return new Aphront404Response();
      }
      return id(new AphrontRedirectResponse())->setURI($file->getInfoURI());
    }
    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    $phid = $file->getPHID();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($file)
      ->setHeader($file->getName());

    $ttl = $file->getTTL();
    if ($ttl !== null) {
      $ttl_tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_STATE)
        ->setBackgroundColor(PHUITagView::COLOR_YELLOW)
        ->setName(pht('Temporary'));
      $header->addTag($ttl_tag);
    }

    $partial = $file->getIsPartial();
    if ($partial) {
      $partial_tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_STATE)
        ->setBackgroundColor(PHUITagView::COLOR_ORANGE)
        ->setName(pht('Partial Upload'));
      $header->addTag($partial_tag);
    }

    $actions = $this->buildActionView($file);
    $timeline = $this->buildTransactionView($file);
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      'F'.$file->getID(),
      $this->getApplicationURI("/info/{$phid}/"));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header);

    $this->buildPropertyViews($object_box, $file, $actions);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $timeline,
      ),
      array(
        'title' => $file->getName(),
        'pageObjects' => array($file->getPHID()),
      ));
  }

  private function buildTransactionView(PhabricatorFile $file) {
    $viewer = $this->getViewer();

    $timeline = $this->buildTransactionTimeline(
      $file,
      new PhabricatorFileTransactionQuery());

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $add_comment_header = $is_serious
      ? pht('Add Comment')
      : pht('Question File Integrity');

    $draft = PhabricatorDraft::newFromUserAndKey($viewer, $file->getPHID());

    $add_comment_form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($file->getPHID())
      ->setDraft($draft)
      ->setHeaderText($add_comment_header)
      ->setAction($this->getApplicationURI('/comment/'.$file->getID().'/'))
      ->setSubmitButtonName(pht('Add Comment'));

    return array(
      $timeline,
      $add_comment_form,
    );
  }

  private function buildActionView(PhabricatorFile $file) {
    $viewer = $this->getViewer();

    $id = $file->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $file,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($file);

    $can_download = !$file->getIsPartial();

    if ($file->isViewableInBrowser()) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View File'))
          ->setIcon('fa-file-o')
          ->setHref($file->getViewURI())
          ->setDisabled(!$can_download)
          ->setWorkflow(!$can_download));
    } else {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setUser($viewer)
          ->setRenderAsForm($can_download)
          ->setDownload($can_download)
          ->setName(pht('Download File'))
          ->setIcon('fa-download')
          ->setHref($file->getViewURI())
          ->setDisabled(!$can_download)
          ->setWorkflow(!$can_download));
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit File'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("/edit/{$id}/"))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Delete File'))
        ->setIcon('fa-times')
        ->setHref($this->getApplicationURI("/delete/{$id}/"))
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Transforms'))
        ->setIcon('fa-crop')
        ->setHref($this->getApplicationURI("/transforms/{$id}/")));

    return $view;
  }

  private function buildPropertyViews(
    PHUIObjectBoxView $box,
    PhabricatorFile $file,
    PhabricatorActionListView $actions) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $properties = id(new PHUIPropertyListView());
    $properties->setActionList($actions);
    $box->addPropertyList($properties, pht('Details'));

    if ($file->getAuthorPHID()) {
      $properties->addProperty(
        pht('Author'),
        $viewer->renderHandle($file->getAuthorPHID()));
    }

    $properties->addProperty(
      pht('Created'),
      phabricator_datetime($file->getDateCreated(), $viewer));


    $finfo = id(new PHUIPropertyListView());
    $box->addPropertyList($finfo, pht('File Info'));

    $finfo->addProperty(
      pht('Size'),
      phutil_format_bytes($file->getByteSize()));

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

    $is_image = $file->isViewableImage();
    if ($is_image) {
      $image_string = pht('Yes');
      $cache_string = $file->getCanCDN() ? pht('Yes') : pht('No');
    } else {
      $image_string = pht('No');
      $cache_string = pht('Not Applicable');
    }

    $finfo->addProperty(pht('Viewable Image'), $image_string);
    $finfo->addProperty(pht('Cacheable'), $cache_string);

    $builtin = $file->getBuiltinName();
    if ($builtin === null) {
      $builtin_string = pht('No');
    } else {
      $builtin_string = $builtin;
    }

    $finfo->addProperty(pht('Builtin'), $builtin_string);

    $is_profile = $file->getIsProfileImage()
      ? pht('Yes')
      : pht('No');

    $finfo->addProperty(pht('Profile'), $is_profile);

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
        $viewer->renderHandleList($phids));
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

    $engine = null;
    try {
      $engine = $file->instantiateStorageEngine();
    } catch (Exception $ex) {
      // Don't bother raising this anywhere for now.
    }

    if ($engine) {
      if ($engine->isChunkEngine()) {
        $chunkinfo = new PHUIPropertyListView();
        $box->addPropertyList($chunkinfo, pht('Chunks'));

        $chunks = id(new PhabricatorFileChunkQuery())
          ->setViewer($viewer)
          ->withChunkHandles(array($file->getStorageHandle()))
          ->execute();
        $chunks = msort($chunks, 'getByteStart');

        $rows = array();
        $completed = array();
        foreach ($chunks as $chunk) {
          $is_complete = $chunk->getDataFilePHID();

          $rows[] = array(
            $chunk->getByteStart(),
            $chunk->getByteEnd(),
            ($is_complete ? pht('Yes') : pht('No')),
          );

          if ($is_complete) {
            $completed[] = $chunk;
          }
        }

        $table = id(new AphrontTableView($rows))
          ->setHeaders(
            array(
              pht('Offset'),
              pht('End'),
              pht('Complete'),
            ))
          ->setColumnClasses(
            array(
              '',
              '',
              'wide',
            ));

        $chunkinfo->addProperty(
          pht('Total Chunks'),
          count($chunks));

        $chunkinfo->addProperty(
          pht('Completed Chunks'),
          count($completed));

        $chunkinfo->addRawContent($table);
      }
    }

  }

}

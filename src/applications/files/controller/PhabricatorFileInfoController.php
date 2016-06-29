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
      ->setHeader($file->getName())
      ->setHeaderIcon('fa-file-o');

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

    $curtain = $this->buildCurtainView($file);
    $timeline = $this->buildTransactionView($file);
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      'F'.$file->getID(),
      $this->getApplicationURI("/info/{$phid}/"));
    $crumbs->setBorder(true);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('File'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);

    $this->buildPropertyViews($object_box, $file);
    $title = $file->getName();

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $object_box,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($file->getPHID()))
      ->appendChild($view);

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

  private function buildCurtainView(PhabricatorFile $file) {
    $viewer = $this->getViewer();

    $id = $file->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $file,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($file);

    $can_download = !$file->getIsPartial();

    if ($file->isViewableInBrowser()) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View File'))
          ->setIcon('fa-file-o')
          ->setHref($file->getViewURI())
          ->setDisabled(!$can_download)
          ->setWorkflow(!$can_download));
    } else {
      $curtain->addAction(
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

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit File'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("/edit/{$id}/"))
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Delete File'))
        ->setIcon('fa-times')
        ->setHref($this->getApplicationURI("/delete/{$id}/"))
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Transforms'))
        ->setIcon('fa-crop')
        ->setHref($this->getApplicationURI("/transforms/{$id}/")));

    return $curtain;
  }

  private function buildPropertyViews(
    PHUIObjectBoxView $box,
    PhabricatorFile $file) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $properties = id(new PHUIPropertyListView());
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

    $types = array();
    if ($file->isViewableImage()) {
      $types[] = pht('Image');
    }

    if ($file->isVideo()) {
      $types[] = pht('Video');
    }

    if ($file->isAudio()) {
      $types[] = pht('Audio');
    }

    if ($file->getCanCDN()) {
      $types[] = pht('Can CDN');
    }

    $builtin = $file->getBuiltinName();
    if ($builtin !== null) {
      $types[] = pht('Builtin ("%s")', $builtin);
    }

    if ($file->getIsProfileImage()) {
      $types[] = pht('Profile');
    }

    if ($types) {
      $types = implode(', ', $types);
      $finfo->addProperty(pht('Attributes'), $types);
    }

    $storage_properties = new PHUIPropertyListView();
    $box->addPropertyList($storage_properties, pht('Storage'));

    $storage_properties->addProperty(
      pht('Engine'),
      $file->getStorageEngine());

    $format_key = $file->getStorageFormat();
    $format = PhabricatorFileStorageFormat::getFormat($format_key);
    if ($format) {
      $format_name = $format->getStorageFormatName();
    } else {
      $format_name = pht('Unknown ("%s")', $format_key);
    }
    $storage_properties->addProperty(pht('Format'), $format_name);

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
    } else if ($file->isVideo()) {
      $video = phutil_tag(
        'video',
        array(
          'controls' => 'controls',
          'class' => 'phui-property-list-video',
        ),
        phutil_tag(
          'source',
          array(
            'src' => $file->getViewURI(),
            'type' => $file->getMimeType(),
          )));
      $media = id(new PHUIPropertyListView())
        ->addImageContent($video);

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

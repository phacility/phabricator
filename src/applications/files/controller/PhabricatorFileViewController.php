<?php

final class PhabricatorFileViewController extends PhabricatorFileController {

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
        ->withIsDeleted(false)
        ->executeOne();

      if (!$file) {
        return new Aphront404Response();
      }
      return id(new AphrontRedirectResponse())->setURI($file->getInfoURI());
    }

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->withIsDeleted(false)
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
        ->setType(PHUITagView::TYPE_SHADE)
        ->setColor(PHUITagView::COLOR_YELLOW)
        ->setName(pht('Temporary'));
      $header->addTag($ttl_tag);
    }

    $partial = $file->getIsPartial();
    if ($partial) {
      $partial_tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_SHADE)
        ->setColor(PHUITagView::COLOR_ORANGE)
        ->setName(pht('Partial Upload'));
      $header->addTag($partial_tag);
    }

    $curtain = $this->buildCurtainView($file);
    $timeline = $this->buildTransactionView($file);
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      $file->getMonogram(),
      $file->getInfoURI());
    $crumbs->setBorder(true);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('File Metadata'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);

    $this->buildPropertyViews($object_box, $file);
    $title = $file->getName();

    $file_content = $this->newFileContent($file);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $file_content,
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

    $comment_view = id(new PhabricatorFileEditEngine())
      ->setViewer($viewer)
      ->buildEditEngineCommentView($file);

    $monogram = $file->getMonogram();

    $timeline->setQuoteRef($monogram);
    $comment_view->setTransactionTimeline($timeline);

    return array(
      $timeline,
      $comment_view,
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
          ->setDownload($can_download)
          ->setName(pht('Download File'))
          ->setIcon('fa-download')
          ->setHref($file->getDownloadURI())
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

    $phids = array();

    $viewer_phid = $viewer->getPHID();
    $author_phid = $file->getAuthorPHID();
    if ($author_phid) {
      $phids[] = $author_phid;
    }

    $handles = $viewer->loadHandles($phids);

    if ($author_phid) {
      $author_refs = id(new PHUICurtainObjectRefListView())
        ->setViewer($viewer);

      $author_ref = $author_refs->newObjectRefView()
        ->setHandle($handles[$author_phid])
        ->setEpoch($file->getDateCreated())
        ->setHighlighted($author_phid === $viewer_phid);

      $curtain->newPanel()
        ->setHeaderText(pht('Authored By'))
        ->appendChild($author_refs);
    }

    $curtain->newPanel()
      ->setHeaderText(pht('Size'))
      ->appendChild(phutil_format_bytes($file->getByteSize()));

    $width = $file->getImageWidth();
    $height = $file->getImageHeight();

    if ($width || $height) {
      $curtain->newPanel()
        ->setHeaderText(pht('Dimensions'))
        ->appendChild(
          pht(
            "%spx \xC3\x97 %spx",
            new PhutilNumber($width),
            new PhutilNumber($height)));
    }

    return $curtain;
  }

  private function buildPropertyViews(
    PHUIObjectBoxView $box,
    PhabricatorFile $file) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $tab_group = id(new PHUITabGroupView());
    $box->addTabGroup($tab_group);

    $finfo = new PHUIPropertyListView();

    $tab_group->addTab(
      id(new PHUITabView())
        ->setName(pht('Details'))
        ->setKey('details')
        ->appendChild($finfo));

    $finfo->addProperty(
      pht('Mime Type'),
      $file->getMimeType());

    $ttl = $file->getTtl();
    if ($ttl) {
      $delta = $ttl - PhabricatorTime::getNow();

      $finfo->addProperty(
        pht('Expires'),
        pht(
          '%s (%s)',
          phabricator_datetime($ttl, $viewer),
          phutil_format_relative_time_detailed($delta)));
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

    $finfo->addProperty(
      pht('Storage Engine'),
      $file->getStorageEngine());

    $engine = $this->loadStorageEngine($file);
    if ($engine && $engine->isChunkEngine()) {
      $format_name = pht('Chunks');
    } else {
      $format_key = $file->getStorageFormat();
      $format = PhabricatorFileStorageFormat::getFormat($format_key);
      if ($format) {
        $format_name = $format->getStorageFormatName();
      } else {
        $format_name = pht('Unknown ("%s")', $format_key);
      }
    }
    $finfo->addProperty(pht('Storage Format'), $format_name);

    $finfo->addProperty(
      pht('Storage Handle'),
      $file->getStorageHandle());

    $custom_alt = $file->getCustomAltText();
    if ($custom_alt !== null && strlen($custom_alt)) {
      $finfo->addProperty(pht('Custom Alt Text'), $custom_alt);
    }

    $default_alt = $file->getDefaultAltText();
    if ($default_alt !== null && strlen($default_alt)) {
      $finfo->addProperty(pht('Default Alt Text'), $default_alt);
    }

    $attachments_table = $this->newAttachmentsView($file);

    $tab_group->addTab(
      id(new PHUITabView())
        ->setName(pht('Attached'))
        ->setKey('attached')
        ->appendChild($attachments_table));

    $engine = $this->loadStorageEngine($file);
    if ($engine) {
      if ($engine->isChunkEngine()) {
        $chunkinfo = new PHUIPropertyListView();

        $tab_group->addTab(
          id(new PHUITabView())
            ->setName(pht('Chunks'))
            ->setKey('chunks')
            ->appendChild($chunkinfo));

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

  private function loadStorageEngine(PhabricatorFile $file) {
    $engine = null;

    try {
      $engine = $file->instantiateStorageEngine();
    } catch (Exception $ex) {
      // Don't bother raising this anywhere for now.
    }

    return $engine;
  }

  private function newFileContent(PhabricatorFile $file) {
    $request = $this->getRequest();

    $ref = id(new PhabricatorDocumentRef())
      ->setFile($file);

    $engine = id(new PhabricatorFileDocumentRenderingEngine())
      ->setRequest($request);

    return $engine->newDocumentView($ref);
  }

  private function newAttachmentsView(PhabricatorFile $file) {
    $viewer = $this->getViewer();

    $attachments = id(new PhabricatorFileAttachmentQuery())
      ->setViewer($viewer)
      ->withFilePHIDs(array($file->getPHID()))
      ->execute();

    $handles = $viewer->loadHandles(mpull($attachments, 'getObjectPHID'));

    $rows = array();

    $mode_map = PhabricatorFileAttachment::getModeNameMap();
    $mode_attach = PhabricatorFileAttachment::MODE_ATTACH;

    foreach ($attachments as $attachment) {
      $object_phid = $attachment->getObjectPHID();
      $handle = $handles[$object_phid];

      $attachment_mode = $attachment->getAttachmentMode();

      $mode_name = idx($mode_map, $attachment_mode);
      if ($mode_name === null) {
        $mode_name = pht('Unknown ("%s")', $attachment_mode);
      }

      $detach_uri = urisprintf(
        '/file/ui/detach/%s/%s/',
        $object_phid,
        $file->getPHID());

      $is_disabled = !$attachment->canDetach();

      $detach_button = id(new PHUIButtonView())
        ->setHref($detach_uri)
        ->setTag('a')
        ->setWorkflow(true)
        ->setDisabled($is_disabled)
        ->setColor(PHUIButtonView::GREY)
        ->setSize(PHUIButtonView::SMALL)
        ->setText(pht('Detach File'));

      javelin_tag(
        'a',
        array(
          'href' => $detach_uri,
          'sigil' => 'workflow',
          'disabled' => true,
          'class' => 'small button button-grey disabled',
        ),
        pht('Detach File'));

      $rows[] = array(
        $handle->renderLink(),
        $mode_name,
        $detach_button,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Attached To'),
          pht('Mode'),
          null,
        ))
      ->setColumnClasses(
        array(
          'pri wide',
          null,
          null,
        ));

    return $table;
  }


}

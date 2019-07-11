<?php

final class PholioMockImagesView extends AphrontView {

  private $mock;
  private $imageID;
  private $requestURI;
  private $commentFormID;

  private $panelID;
  private $viewportID;
  private $behaviorConfig;

  public function setCommentFormID($comment_form_id) {
    $this->commentFormID = $comment_form_id;
    return $this;
  }

  public function getCommentFormID() {
    return $this->commentFormID;
  }

  public function setRequestURI(PhutilURI $request_uri) {
    $this->requestURI = $request_uri;
    return $this;
  }

  public function getRequestURI() {
    return $this->requestURI;
  }

  public function setImageID($image_id) {
    $this->imageID = $image_id;
    return $this;
  }

  public function getImageID() {
    return $this->imageID;
  }

  public function setMock(PholioMock $mock) {
    $this->mock = $mock;
    return $this;
  }

  public function getMock() {
    return $this->mock;
  }

  public function __construct() {
    $this->panelID = celerity_generate_unique_node_id();
    $this->viewportID = celerity_generate_unique_node_id();
  }

  public function getBehaviorConfig() {
    if (!$this->getMock()) {
      throw new PhutilInvalidStateException('setMock');
    }

    if ($this->behaviorConfig === null) {
      $this->behaviorConfig = $this->calculateBehaviorConfig();
    }
    return $this->behaviorConfig;
  }

  private function calculateBehaviorConfig() {
    $mock = $this->getMock();

    // TODO: We could maybe do a better job with tailoring this, which is the
    // image shown on the review stage.
    $viewer = $this->getUser();

    $default = PhabricatorFile::loadBuiltin($viewer, 'image-100x100.png');

    $images = array();
    $current_set = 0;
    foreach ($mock->getImages() as $image) {
      $file = $image->getFile();
      $metadata = $file->getMetadata();
      $x = idx($metadata, PhabricatorFile::METADATA_IMAGE_WIDTH);
      $y = idx($metadata, PhabricatorFile::METADATA_IMAGE_HEIGHT);

      $is_obs = (bool)$image->getIsObsolete();
      if (!$is_obs) {
        $current_set++;
      }

      $description = $image->getDescription();
      if (strlen($description)) {
        $description = new PHUIRemarkupView($viewer, $description);
      }

      $history_uri = '/pholio/image/history/'.$image->getID().'/';
      $images[] = array(
        'id' => $image->getID(),
        'fullURI' => $file->getBestURI(),
        'stageURI' => ($file->isViewableImage()
          ? $file->getBestURI()
          : $default->getBestURI()),
        'pageURI' => $this->getImagePageURI($image, $mock),
        'downloadURI' => $file->getDownloadURI(),
        'historyURI' => $history_uri,
        'width' => $x,
        'height' => $y,
        'title' => $image->getName(),
        'descriptionMarkup' => hsprintf('%s', $description),
        'isObsolete' => (bool)$image->getIsObsolete(),
        'isImage' => $file->isViewableImage(),
        'isViewable' => $file->isViewableInBrowser(),
      );
    }

    $ids = mpull($mock->getActiveImages(), null, 'getID');
    if ($this->imageID && isset($ids[$this->imageID])) {
      $selected_id = $this->imageID;
    } else {
      $selected_id = head_key($ids);
    }

    $navsequence = array();
    foreach ($mock->getActiveImages() as $image) {
      $navsequence[] = $image->getID();
    }

    $full_icon = array(
      javelin_tag('span', array('aural' => true), pht('View Raw File')),
      id(new PHUIIconView())->setIcon('fa-file-image-o'),
    );

    $download_icon = array(
      javelin_tag('span', array('aural' => true), pht('Download File')),
      id(new PHUIIconView())->setIcon('fa-download'),
    );

    $login_uri = id(new PhutilURI('/login/'))
      ->replaceQueryParam('next', (string)$this->getRequestURI());

    $config = array(
      'mockID' => $mock->getID(),
      'panelID' => $this->panelID,
      'viewportID' => $this->viewportID,
      'commentFormID' => $this->getCommentFormID(),
      'images' => $images,
      'selectedID' => $selected_id,
      'loggedIn' => $this->getUser()->isLoggedIn(),
      'logInLink' => (string)$login_uri,
      'navsequence' => $navsequence,
      'fullIcon' => hsprintf('%s', $full_icon),
      'downloadIcon' => hsprintf('%s', $download_icon),
      'currentSetSize' => $current_set,
    );
    return $config;
  }

  public function render() {
    if (!$this->getMock()) {
      throw new PhutilInvalidStateException('setMock');
    }
    $mock = $this->getMock();

    require_celerity_resource('javelin-behavior-pholio-mock-view');

    $panel_id = $this->panelID;
    $viewport_id = $this->viewportID;

    $config = $this->getBehaviorConfig();
    Javelin::initBehavior(
      'pholio-mock-view',
      $this->getBehaviorConfig());

    $mock_wrapper = javelin_tag(
      'div',
      array(
        'id' => $this->viewportID,
        'sigil' => 'mock-viewport',
        'class' => 'pholio-mock-image-viewport',
      ),
      '');

    $image_header = javelin_tag(
      'div',
      array(
        'id' => 'mock-image-header',
        'class' => 'pholio-mock-image-header',
      ),
      '');

    $mock_wrapper = javelin_tag(
      'div',
      array(
        'id' => $this->panelID,
        'sigil' => 'mock-panel touchable',
        'class' => 'pholio-mock-image-panel',
      ),
      array(
        $image_header,
        $mock_wrapper,
      ));

    $inline_comments_holder = javelin_tag(
      'div',
      array(
        'id' => 'mock-image-description',
        'sigil' => 'mock-image-description',
        'class' => 'mock-image-description',
      ),
      '');

    return phutil_tag(
      'div',
        array(
          'class' => 'pholio-mock-image-container',
          'id' => 'pholio-mock-image-container',
        ),
      array($mock_wrapper, $inline_comments_holder));

  }

  private function getImagePageURI(PholioImage $image, PholioMock $mock) {
    $uri = '/M'.$mock->getID().'/'.$image->getID().'/';
    return $uri;
  }
}

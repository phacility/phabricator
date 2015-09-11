<?php

final class PhrictionDocument extends PhrictionDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface,
    PhabricatorFlaggableInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorDestructibleInterface,
    PhabricatorApplicationTransactionInterface {

  protected $slug;
  protected $depth;
  protected $contentID;
  protected $status;
  protected $mailKey;
  protected $viewPolicy;
  protected $editPolicy;

  private $contentObject = self::ATTACHABLE;
  private $ancestors = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'slug' => 'sort128',
        'depth' => 'uint32',
        'contentID' => 'id?',
        'status' => 'uint32',
        'mailKey' => 'bytes20',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'slug' => array(
          'columns' => array('slug'),
          'unique' => true,
        ),
        'depth' => array(
          'columns' => array('depth', 'slug'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhrictionDocumentPHIDType::TYPECONST);
  }

  public static function initializeNewDocument(PhabricatorUser $actor, $slug) {
    $document = new PhrictionDocument();
    $document->setSlug($slug);

    $content  = new PhrictionContent();
    $content->setSlug($slug);

    $default_title = PhabricatorSlug::getDefaultTitle($slug);
    $content->setTitle($default_title);
    $document->attachContent($content);

    $parent_doc = null;
    $ancestral_slugs = PhabricatorSlug::getAncestry($slug);
    if ($ancestral_slugs) {
      $parent = end($ancestral_slugs);
      $parent_doc = id(new PhrictionDocumentQuery())
        ->setViewer($actor)
        ->withSlugs(array($parent))
        ->executeOne();
    }

    if ($parent_doc) {
      $document->setViewPolicy($parent_doc->getViewPolicy());
      $document->setEditPolicy($parent_doc->getEditPolicy());
    } else {
      $default_view_policy = PhabricatorPolicies::getMostOpenPolicy();
      $document->setViewPolicy($default_view_policy);
      $document->setEditPolicy(PhabricatorPolicies::POLICY_USER);
    }

    return $document;
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public static function getSlugURI($slug, $type = 'document') {
    static $types = array(
      'document'  => '/w/',
      'history'   => '/phriction/history/',
    );

    if (empty($types[$type])) {
      throw new Exception(pht("Unknown URI type '%s'!", $type));
    }

    $prefix = $types[$type];

    if ($slug == '/') {
      return $prefix;
    } else {
      // NOTE: The effect here is to escape non-latin characters, since modern
      // browsers deal with escaped UTF8 characters in a reasonable way (showing
      // the user a readable URI) but older programs may not.
      $slug = phutil_escape_uri($slug);
      return $prefix.$slug;
    }
  }

  public function setSlug($slug) {
    $this->slug   = PhabricatorSlug::normalize($slug);
    $this->depth  = PhabricatorSlug::getDepth($slug);
    return $this;
  }

  public function attachContent(PhrictionContent $content) {
    $this->contentObject = $content;
    return $this;
  }

  public function getContent() {
    return $this->assertAttached($this->contentObject);
  }

  public function getAncestors() {
    return $this->ancestors;
  }

  public function getAncestor($slug) {
    return $this->assertAttachedKey($this->ancestors, $slug);
  }

  public function attachAncestor($slug, $ancestor) {
    $this->ancestors[$slug] = $ancestor;
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    return false;
  }

  public function describeAutomaticCapability($capability) {

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return pht(
          'To view a wiki document, you must also be able to view all '.
          'of its parents.');
      case PhabricatorPolicyCapability::CAN_EDIT:
        return pht(
          'To edit a wiki document, you must also be able to view all '.
          'of its parents.');
    }

    return null;
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return false;
  }

  public function shouldShowSubscribersProperty() {
    return true;
  }

  public function shouldAllowSubscription($phid) {
    return true;
  }

/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhrictionTransactionEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhrictionTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return PhabricatorSubscribersQuery::loadSubscribersForPHID($this->phid);
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();

      $this->delete();

      $contents = id(new PhrictionContent())->loadAllWhere(
        'documentID = %d',
        $this->getID());
      foreach ($contents as $content) {
        $content->delete();
      }

    $this->saveTransaction();
  }

}

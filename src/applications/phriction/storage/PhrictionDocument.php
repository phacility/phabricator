<?php

final class PhrictionDocument extends PhrictionDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface,
    PhabricatorFlaggableInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorDestructibleInterface,
    PhabricatorFulltextInterface,
    PhabricatorFerretInterface,
    PhabricatorProjectInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorConduitResultInterface,
    PhabricatorPolicyCodexInterface,
    PhabricatorSpacesInterface {

  protected $slug;
  protected $depth;
  protected $contentPHID;
  protected $status;
  protected $viewPolicy;
  protected $editPolicy;
  protected $spacePHID;
  protected $editedEpoch;
  protected $maxVersion;

  private $contentObject = self::ATTACHABLE;
  private $ancestors = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'slug' => 'sort128',
        'depth' => 'uint32',
        'status' => 'text32',
        'editedEpoch' => 'epoch',
        'maxVersion' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
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

  public function getPHIDType() {
    return PhrictionDocumentPHIDType::TYPECONST;
  }

  public static function initializeNewDocument(PhabricatorUser $actor, $slug) {
    $document = id(new self())
      ->setSlug($slug);

    $content = id(new PhrictionContent())
      ->setSlug($slug);

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
      $space_phid = PhabricatorSpacesNamespaceQuery::getObjectSpacePHID(
        $parent_doc);

      $document
        ->setViewPolicy($parent_doc->getViewPolicy())
        ->setEditPolicy($parent_doc->getEditPolicy())
        ->setSpacePHID($space_phid);
    } else {
      $default_view_policy = PhabricatorPolicies::getMostOpenPolicy();
      $document
        ->setViewPolicy($default_view_policy)
        ->setEditPolicy(PhabricatorPolicies::POLICY_USER)
        ->setSpacePHID($actor->getDefaultSpacePHID());
    }

    $document->setEditedEpoch(PhabricatorTime::getNow());
    $document->setMaxVersion(0);

    return $document;
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

  public function getURI() {
    return self::getSlugURI($this->getSlug());
  }

/* -(  Status  )------------------------------------------------------------- */


  public function getStatusObject() {
    return PhrictionDocumentStatus::newStatusObject($this->getStatus());
  }

  public function getStatusIcon() {
    return $this->getStatusObject()->getIcon();
  }

  public function getStatusColor() {
    return $this->getStatusObject()->getColor();
  }

  public function getStatusDisplayName() {
    return $this->getStatusObject()->getDisplayName();
  }

  public function isActive() {
    return $this->getStatusObject()->isActive();
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


/* -(  PhabricatorSpacesInterface  )----------------------------------------- */


  public function getSpacePHID() {
    return $this->spacePHID;
  }



/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return false;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhrictionTransactionEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhrictionTransaction();
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return PhabricatorSubscribersQuery::loadSubscribersForPHID($this->phid);
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();

      $contents = id(new PhrictionContentQuery())
        ->setViewer($engine->getViewer())
        ->withDocumentPHIDs(array($this->getPHID()))
        ->execute();
      foreach ($contents as $content) {
        $engine->destroyObject($content);
      }

      $this->delete();

    $this->saveTransaction();
  }


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new PhrictionDocumentFulltextEngine();
  }


/* -(  PhabricatorFerretInterface  )----------------------------------------- */


  public function newFerretEngine() {
    return new PhrictionDocumentFerretEngine();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('path')
        ->setType('string')
        ->setDescription(pht('The path to the document.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('status')
        ->setType('map<string, wild>')
        ->setDescription(pht('Status information about the document.')),
    );
  }

  public function getFieldValuesForConduit() {
    $status = array(
      'value' => $this->getStatus(),
      'name' => $this->getStatusDisplayName(),
    );

    return array(
      'path' => $this->getSlug(),
      'status' => $status,
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new PhrictionContentSearchEngineAttachment())
        ->setAttachmentKey('content'),
    );
  }

/* -(  PhabricatorPolicyCodexInterface  )------------------------------------ */


  public function newPolicyCodex() {
    return new PhrictionDocumentPolicyCodex();
  }


}

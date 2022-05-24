<?php

final class PhabricatorMacroQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $names;
  private $nameLike;
  private $namePrefix;
  private $dateCreatedAfter;
  private $dateCreatedBefore;
  private $flagColor;

  private $needFiles;

  private $status = 'status-any';
  const STATUS_ANY = 'status-any';
  const STATUS_ACTIVE = 'status-active';
  const STATUS_DISABLED = 'status-disabled';

  public static function getStatusOptions() {
    return array(
      self::STATUS_ACTIVE   => pht('Active Macros'),
      self::STATUS_DISABLED => pht('Disabled Macros'),
      self::STATUS_ANY      => pht('Active and Disabled Macros'),
    );
  }

  public static function getFlagColorsOptions() {
    $options = array(
      '-1' => pht('(No Filtering)'),
      '-2' => pht('(Marked With Any Flag)'),
    );

    foreach (PhabricatorFlagColor::getColorNameMap() as $color => $name) {
      $options[$color] = $name;
    }

    return $options;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs(array $author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  public function withNameLike($name) {
    $this->nameLike = $name;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function withNamePrefix($prefix) {
    $this->namePrefix = $prefix;
    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function withDateCreatedBefore($date_created_before) {
    $this->dateCreatedBefore = $date_created_before;
    return $this;
  }

  public function withDateCreatedAfter($date_created_after) {
    $this->dateCreatedAfter = $date_created_after;
    return $this;
  }

  public function withFlagColor($flag_color) {
    $this->flagColor = $flag_color;
    return $this;
  }

  public function needFiles($need_files) {
    $this->needFiles = $need_files;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorFileImageMacro();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'm.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'm.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'm.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if (($this->nameLike !== null) && strlen($this->nameLike)) {
      $where[] = qsprintf(
        $conn,
        'm.name LIKE %~',
        $this->nameLike);
    }

    if ($this->names !== null) {
      $where[] = qsprintf(
        $conn,
        'm.name IN (%Ls)',
        $this->names);
    }

    if (($this->namePrefix !== null) && strlen($this->namePrefix)) {
      $where[] = qsprintf(
        $conn,
        'm.name LIKE %>',
        $this->namePrefix);
    }

    switch ($this->status) {
      case self::STATUS_ACTIVE:
        $where[] = qsprintf(
          $conn,
          'm.isDisabled = 0');
        break;
      case self::STATUS_DISABLED:
        $where[] = qsprintf(
          $conn,
          'm.isDisabled = 1');
        break;
      case self::STATUS_ANY:
        break;
      default:
        throw new Exception(pht("Unknown status '%s'!", $this->status));
    }

    if ($this->dateCreatedAfter) {
      $where[] = qsprintf(
        $conn,
        'm.dateCreated >= %d',
        $this->dateCreatedAfter);
    }

    if ($this->dateCreatedBefore) {
      $where[] = qsprintf(
        $conn,
        'm.dateCreated <= %d',
        $this->dateCreatedBefore);
    }

    if ($this->flagColor != '-1' && $this->flagColor !== null) {
      if ($this->flagColor == '-2') {
        $flag_colors = array_keys(PhabricatorFlagColor::getColorNameMap());
      } else {
        $flag_colors = array($this->flagColor);
      }
      $flags = id(new PhabricatorFlagQuery())
        ->withOwnerPHIDs(array($this->getViewer()->getPHID()))
        ->withTypes(array(PhabricatorMacroMacroPHIDType::TYPECONST))
        ->withColors($flag_colors)
        ->setViewer($this->getViewer())
        ->execute();

      if (empty($flags)) {
        throw new PhabricatorEmptyQueryException(pht('No matching flags.'));
      } else {
        $where[] = qsprintf(
          $conn,
          'm.phid IN (%Ls)',
          mpull($flags, 'getObjectPHID'));
      }
    }

    return $where;
  }

  protected function didFilterPage(array $macros) {
    if ($this->needFiles) {
      $file_phids = mpull($macros, 'getFilePHID');
      $files = id(new PhabricatorFileQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withPHIDs($file_phids)
        ->execute();
      $files = mpull($files, null, 'getPHID');

      foreach ($macros as $key => $macro) {
        $file = idx($files, $macro->getFilePHID());
        if (!$file) {
          unset($macros[$key]);
          continue;
        }
        $macro->attachFile($file);
      }
    }

    return $macros;
  }

  protected function getPrimaryTableAlias() {
    return 'm';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorMacroApplication';
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'name' => array(
        'table' => 'm',
        'column' => 'name',
        'type' => 'string',
        'reverse' => true,
        'unique' => true,
      ),
    );
  }

  protected function newPagingMapFromPartialObject($object) {
    return array(
      'id' => (int)$object->getID(),
      'name' => $object->getName(),
    );
  }

  public function getBuiltinOrders() {
    return array(
      'name' => array(
        'vector' => array('name'),
        'name' => pht('Name'),
      ),
    ) + parent::getBuiltinOrders();
  }

}

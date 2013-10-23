<?php

/**
 * @group phriction
 */
final class PhabricatorMacroQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authors;
  private $names;
  private $nameLike;
  private $dateCreatedAfter;
  private $dateCreatedBefore;
  private $flagColor;

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

    $options = array('-1' => pht('(No Filtering)'));

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

  public function withAuthorPHIDs(array $authors) {
    $this->authors = $authors;
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

  protected function loadPage() {
    $macro_table = new PhabricatorFileImageMacro();
    $conn = $macro_table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT m.* FROM %T m %Q %Q %Q',
      $macro_table->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $macro_table->loadAllFromArray($rows);
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn,
        'm.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn,
        'm.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authors) {
      $where[] = qsprintf(
        $conn,
        'm.authorPHID IN (%Ls)',
        $this->authors);
    }

    if ($this->nameLike) {
      $where[] = qsprintf(
        $conn,
        'm.name LIKE %~',
        $this->nameLike);
    }

    if ($this->names) {
      $where[] = qsprintf(
        $conn,
        'm.name IN (%Ls)',
        $this->names);
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
        throw new Exception("Unknown status '{$this->status}'!");
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
      $flags = id(new PhabricatorFlagQuery())
        ->withOwnerPHIDs(array($this->getViewer()->getPHID()))
        ->withTypes(array(PhabricatorMacroPHIDTypeMacro::TYPECONST))
        ->withColors(array($this->flagColor))
        ->setViewer($this->getViewer())
        ->execute();

      if (empty($flags)) {
        throw new PhabricatorEmptyQueryException('No matching flags.');
      } else {
        $where[] = qsprintf(
          $conn,
          'm.phid IN (%Ls)',
          mpull($flags, 'getObjectPHID'));
      }
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($where);
  }

  protected function didFilterPage(array $macros) {
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

    return $macros;
  }

  protected function getPagingColumn() {
    return 'm.id';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationMacro';
  }

}

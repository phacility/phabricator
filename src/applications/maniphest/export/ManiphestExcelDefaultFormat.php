<?php

final class ManiphestExcelDefaultFormat extends ManiphestExcelFormat {

  public function getName() {
    return pht('Default');
  }

  public function getFileName() {
    return 'maniphest_tasks_'.date('Ymd');
  }

  /**
   * @phutil-external-symbol class PHPExcel
   * @phutil-external-symbol class PHPExcel_IOFactory
   * @phutil-external-symbol class PHPExcel_Style_NumberFormat
   * @phutil-external-symbol class PHPExcel_Cell_DataType
   */
  public function buildWorkbook(
    PHPExcel $workbook,
    array $tasks,
    array $handles,
    PhabricatorUser $user) {

    $sheet = $workbook->setActiveSheetIndex(0);
    $sheet->setTitle(pht('Tasks'));

    $widths = array(
      null,
      15,
      null,
      10,
      15,
      15,
      60,
      30,
      20,
      100,
    );

    foreach ($widths as $col => $width) {
      if ($width !== null) {
        $sheet->getColumnDimension($this->col($col))->setWidth($width);
      }
    }

    $status_map = ManiphestTaskStatus::getTaskStatusMap();
    $pri_map = ManiphestTaskPriority::getTaskPriorityMap();

    $date_format = null;

    $rows = array();
    $rows[] = array(
      pht('ID'),
      pht('Owner'),
      pht('Status'),
      pht('Priority'),
      pht('Date Created'),
      pht('Date Updated'),
      pht('Title'),
      pht('Projects'),
      pht('URI'),
      pht('Description'),
    );

    $is_date = array(
      false,
      false,
      false,
      false,
      true,
      true,
      false,
      false,
      false,
      false,
    );

    $header_format = array(
      'font'  => array(
        'bold' => true,
      ),
    );

    foreach ($tasks as $task) {
      $task_owner = null;
      if ($task->getOwnerPHID()) {
        $task_owner = $handles[$task->getOwnerPHID()]->getName();
      }

      $projects = array();
      foreach ($task->getProjectPHIDs() as $phid) {
        $projects[] = $handles[$phid]->getName();
      }
      $projects = implode(', ', $projects);

      $rows[] = array(
        'T'.$task->getID(),
        $task_owner,
        idx($status_map, $task->getStatus(), '?'),
        idx($pri_map, $task->getPriority(), '?'),
        $this->computeExcelDate($task->getDateCreated()),
        $this->computeExcelDate($task->getDateModified()),
        $task->getTitle(),
        $projects,
        PhabricatorEnv::getProductionURI('/T'.$task->getID()),
        id(new PhutilUTF8StringTruncator())
        ->setMaximumBytes(512)
        ->truncateString($task->getDescription()),
      );
    }

    foreach ($rows as $row => $cols) {
      foreach ($cols as $col => $spec) {
        $cell_name = $this->col($col).($row + 1);
        $cell = $sheet
          ->setCellValue($cell_name, $spec, $return_cell = true);

        if ($row == 0) {
          $sheet->getStyle($cell_name)->applyFromArray($header_format);
        }

        if ($is_date[$col]) {
          $code = PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2;
          $sheet
            ->getStyle($cell_name)
            ->getNumberFormat()
            ->setFormatCode($code);
        } else {
          $cell->setDataType(PHPExcel_Cell_DataType::TYPE_STRING);
        }
      }
    }
  }

  private function col($n) {
    return chr(ord('A') + $n);
  }

}

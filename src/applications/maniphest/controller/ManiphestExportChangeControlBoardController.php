<?php

/**
 * @group maniphest
 */
final class ManiphestExportChangeControlBoardController extends ManiphestController {

  private $key;

  public function willProcessRequest(array $data) {
    $this->key = $data['key'];
    return $this;
  }

  /**
   * @phutil-external-symbol class PHPExcel
   * @phutil-external-symbol class PHPExcel_IOFactory
   * @phutil-external-symbol class PHPExcel_Style_NumberFormat
   */
  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $extensions = ManiphestTaskExtensions::newExtensions();
    $aux_fields = $extensions->getAuxiliaryFieldSpecifications();

    $ok = @include_once 'PHPExcel.php';
    if (!$ok) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($user);

      $dialog->setTitle('Excel Export Not Configured');
      $dialog->appendChild(
        '<p>This system does not have PHPExcel installed. This software '.
        'component is required to export change control board to Excel. Have your system '.
        'administrator install it from:</p>'.
        '<br />'.
        '<p>'.
          '<a href="http://www.phpexcel.net/">http://www.phpexcel.net/</a>'.
        '</p>'.
        '<br />'.
        '<p>Your PHP "include_path" needs to be updated to include the '.
        'PHPExcel Classes/ directory.</p>');

      $dialog->addCancelButton('/maniphest/');
      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $ccb_enabled = PhabricatorEnv::getEnvConfig('maniphest.change-control-board.enabled');
    $ccb_get_headers = PhabricatorEnv::getEnvConfig('maniphest.change-control-board.get-columns');
    $ccb_get_fields = PhabricatorEnv::getEnvConfig('maniphest.change-control-board.get-fields');
    $ccb_filter = PhabricatorEnv::getEnvConfig('maniphest.change-control-board.filter');
    $ccb_filename = PhabricatorEnv::getEnvConfig('maniphest.change-control-board.filename');

    if (!$ccb_enabled) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($user);

      $dialog->setTitle('Change Control Board Not Enabled');
      $dialog->appendChild(
        '<p>This system does not have Change Control Board enabled.  Please enable '.
        'and configure it in the Phabricator configuration file.</p>');

      $dialog->addCancelButton('/maniphest/');
      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $query = id(new PhabricatorSearchQuery())->loadOneWhere(
      'queryKey = %s',
      $this->key);
    if (!$query) {
      return new Aphront404Response();
    }

    if (!$request->isDialogFormPost()) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($user);

      $dialog->setTitle('Export Change Control Board');
      $dialog->appendChild(
        '<p>Do you want to export the change control board results to Excel?</p>');

      $dialog->addCancelButton('/maniphest/');
      $dialog->addSubmitButton('Export to Excel');
      return id(new AphrontDialogResponse())->setDialog($dialog);

    }

    $query->setParameter('limit',   null);
    $query->setParameter('offset',  null);
    $query->setParameter('order',   'p');
    $query->setParameter('group',   'n');

    list($tasks, $handles) = ManiphestTaskListController::loadTasks($query, $aux_fields);
    // Ungroup tasks.
    $tasks = array_mergev($tasks);

    $all_projects = array_mergev(mpull($tasks, 'getProjectPHIDs'));
    $project_handles = $this->loadViewerHandles($all_projects);
    $handles += $project_handles;

    $workbook = new PHPExcel();

    $sheet = $workbook->setActiveSheetIndex(0);
    $sheet->setTitle('Tasks');

    $headers = $ccb_get_headers();

    $widths = array();
    $is_date = array();
    $i = 0;
    foreach ($headers as $name => $settings) {
      if (array_key_exists('width', $settings)) {
        $widths[$i] = $settings['width'];
      } else {
        $widths[$i] = null;
      }
      if (array_key_exists('date', $settings)) {
        $is_date[$i] = $settings['date'];
      } else {
        $is_date[$i] = false;
      }
      $i++;
    }

    foreach ($widths as $col => $width) {
      if ($width !== null) {
        $sheet->getColumnDimension($this->col($col))->setWidth($width);
      }
    }

    $status_map = ManiphestTaskStatus::getTaskStatusMap();
    $pri_map = ManiphestTaskPriority::getTaskPriorityMap();

    $date_format = null;

    $rows = array();
    $first_row = array();
    $i = 0;
    foreach ($headers as $name => $settings) {
      $first_row[$i++] = $name;
    }
    $rows[] = $first_row;

    $header_format = PhabricatorEnv::getEnvConfig('maniphest.change-control-board.header-format');

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

      // Check if current status is one of the deployment statuses.
      $task->loadAndAttachAuxiliaryAttributes();
      if (!$ccb_filter($task)) {
        continue;
      }

      $rows[] = $ccb_get_fields(array(
        "ccb" => $this,
        "task" => $task,
        "task_owner" => $task_owner,
        "status_map" => $status_map,
        "pri_map" => $pri_map,
        "projects" => $projects,
        "aux_fields" => $aux_fields,
      ));
    }

    foreach ($rows as $row => $cols) {
      foreach ($cols as $col => $spec) {
        $cell_name = $this->col($col).($row + 1);
        $sheet->setCellValue($cell_name, $spec);
        $sheet->getStyle($cell_name)->getAlignment()->setWrapText(true);

        if ($row == 0) {
          $sheet->getStyle($cell_name)->applyFromArray($header_format);
        }

        if ($is_date[$col]) {
          $code = PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2;
          $sheet
            ->getStyle($cell_name)
            ->getNumberFormat()
            ->setFormatCode($code);
        }
      }
    }

    $writer = PHPExcel_IOFactory::createWriter($workbook, 'Excel2007');

    ob_start();
    $writer->save('php://output');
    $data = ob_get_clean();

    $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    return id(new AphrontFileResponse())
      ->setMimeType($mime)
      ->setDownload($ccb_filename . 'change_control_board_'.date('Ymd').'.xlsx')
      ->setContent($data);
  }

  public function renderAuxiliaryAttribute($task, $field, $aux_fields) {
    $request = $this->getRequest();
    $user = $request->getUser();
    foreach ($aux_fields as $aux_field) {
      if ($aux_field->getAuxiliaryKey() == $field) {
        $aux_field->setValue($task->getAuxiliaryAttribute($aux_field->getAuxiliaryKey()));
        return $aux_field->renderForDetailView($user);
      }
    }
    return $aux_field->getAuxiliaryKey();
  }

  private function computeExcelDate($epoch) {
    $seconds_per_day = (60 * 60 * 24);
    $offset = ($seconds_per_day * 25569);

    return ($epoch + $offset) / $seconds_per_day;
  }

  private function col($n) {
    if ($n >= 26) {
      $a = floor($n / 26) - 1;
      $b = $n % 26;
      return chr(ord('A') + $a) . chr(ord('A') + $b);
    }
    else
      return chr(ord('A') + $n);
  }

}

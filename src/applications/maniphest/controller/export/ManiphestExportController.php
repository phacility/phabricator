<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group maniphest
 */
final class ManiphestExportController extends ManiphestController {

  private $key;

  public function willProcessRequest(array $data) {
    $this->key = $data['key'];
    return $this;
  }

  /**
   * @phutil-external-symbol class Spreadsheet_Excel_Writer
   */
  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $ok = @include_once 'Spreadsheet/Excel/Writer.php';
    if (!$ok) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($user);

      $dialog->setTitle('Excel Export Not Configured');
      $dialog->appendChild(
        '<p>This system does not have Spreadsheet_Excel_Writer installed. '.
        'This software component is required to export tasks to Excel. Have '.
        'your system administrator install it with:</p>'.
        '<br />'.
        '<p><code>$ sudo pear install Spreadsheet_Excel_Writer</code></p>');

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

      $dialog->setTitle('Export Tasks to Excel');
      $dialog->appendChild(
        '<p>Do you want to export the query results to Excel?</p>');

      $dialog->addCancelButton('/maniphest/');
      $dialog->addSubmitButton('Export to Excel');
      return id(new AphrontDialogResponse())->setDialog($dialog);

    }

    $query->setParameter('limit',   null);
    $query->setParameter('offset',  null);
    $query->setParameter('order',   'p');
    $query->setParameter('group',   'n');

    list($tasks, $handles) = ManiphestTaskListController::loadTasks($query);
    // Ungroup tasks.
    $tasks = array_mergev($tasks);

    $all_projects = array_mergev(mpull($tasks, 'getProjectPHIDs'));
    $project_handles = id(new PhabricatorObjectHandleData($all_projects))
      ->loadHandles();
    $handles += $project_handles;

    $workbook = new Spreadsheet_Excel_Writer();
    $sheet = $workbook->addWorksheet('Exported Maniphest Tasks');

    $date_format = $workbook->addFormat();
    $date_format->setNumFormat('M/D/YYYY h:mm AM/PM');

    $widths = array(
      null,
      20,
      null,
      15,
      20,
      20,
      75,
      40,
      30,
      400,
    );

    foreach ($widths as $col => $width) {
      if ($width !== null) {
        $sheet->setColumn($col, $col, $width);
      }
    }

    $status_map = ManiphestTaskStatus::getTaskStatusMap();
    $pri_map = ManiphestTaskPriority::getTaskPriorityMap();

    $rows = array();
    $rows[] = array(
      'ID',
      'Owner',
      'Status',
      'Priority',
      'Date Created',
      'Date Updated',
      'Title',
      'Projects',
      'URI',
      'Description',
    );
    $formats = array(
      null,
      null,
      null,
      null,
      $date_format,
      $date_format,
      null,
      null,
      null,
      null,
    );

    $header_format = $workbook->addFormat();
    $header_format->setBold();

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
        $task->getDescription(),
      );
    }

    foreach ($rows as $row => $cols) {
      foreach ($cols as $col => $spec) {
        if ($row == 0) {
          $fmt = $header_format;
        } else {
          $fmt = $formats[$col];
        }
        $sheet->write($row, $col, $spec, $fmt);
      }
    }

    ob_start();
    $workbook->close();
    $data = ob_get_clean();

    return id(new AphrontFileResponse())
      ->setMimeType('application/vnd.ms-excel')
      ->setDownload('maniphest_tasks_'.date('Ymd').'.xls')
      ->setContent($data);
  }

  private function computeExcelDate($epoch) {
    $seconds_per_day = (60 * 60 * 24);
    $offset = ($seconds_per_day * 25569);

    return ($epoch + $offset) / $seconds_per_day;
  }

}

<?php

class TimeTrackerMainPanelRequestHandler extends TimeTrackerRequestHandler
{

    private $numMinutes = 0;
    private $numHours = 0;
    private $responsePanel = null;
    private $request = null;
    private $projectName = null;

    public function handleRequest($request)
    {
        $this->request = $request;

        $isSent = $request->getStr('isSent') == '1';


        $projectPHID = $this->tryGetProject($request);

        if ($projectPHID != null) {       
            $this->projectName = TimeTrackerStorageManager::getNameSelectedProject($projectPHID);
        }else{
            $this->responsePanel = $this->createFeedbackMessage();
        }

        if ($isSent && $projectPHID != null) {
            $correctRequest = $this->parseTrackTimeRequest($request, $projectPHID);

            if (!$correctRequest) {
                $this->responsePanel = $this->createResponsePanel(false);
            } else {
                $date = $request->getStr('date');
                $date = trim($date);
                $pieces = explode('/', $date);

                $day = $pieces[1];
                $month = $pieces[0];
                $year = $pieces[2];

                $manager = new TimeTrackerStorageManager();
                $manager->trackTime($request->getUser(), $this->numHours, $this->numMinutes, $day, $month, $year, $projectPHID);

                $this->responsePanel = $this->createResponsePanel(true);
            }
        }
    }

    public function parseTrackTimeRequest($request, $projectPHID)
    {
        $timeTracked = $request->getStr('timeTracked');

        $timeTracked = trim($timeTracked);
        $timeTracked = strtolower($timeTracked);

        if (!strpbrk($timeTracked, '0123456789')) {
            return false;
        }

        $hasMinutes = strpos($timeTracked, 'm') !== false;
        $hasHours = strpos($timeTracked, 'h') !== false;
        $isNegative = strcmp(substr($timeTracked, 0, 1), '-') == 0;
        $isRange = (strpos($timeTracked, '-') !== false) && !$isNegative;

        if (!$hasMinutes && !$hasHours && !$isRange) {
            return false;
        }

        $date = $request->getStr('date');
        $date = trim($date);
        $pieces = explode('/', $date);

        $day = $pieces[1];
        $month = $pieces[0];
        $year = $pieces[2];

        $date = TimeTrackerTimeUtils::getTimestamp($day, $month, $year);

        $correctInput = true;
        if ($isRange) {
            $correctInput = $this->parseRange($timeTracked);
        } else {
            $correctInput = $this->parseSingleTimeInput($timeTracked, $hasMinutes, $hasHours, $isNegative);
        }

        $numMinutesToTrack = $this->numMinutes + $this->numHours * 60;
        $numMinutesToTrackFromDay = TimeTrackerStorageManager::getNumMinutesTrackedFromDate($request->getUser(), $date, $projectPHID);

        // $numMinutesAlreadyTrackedToday = TimeTrackerStorageManager::getNumMinutesTrackedToday($request->getUser());

        if ($numMinutesToTrackFromDay + $numMinutesToTrack < 0 || $numMinutesToTrack == 0) {
            $correctInput = false;
        }

        return $correctInput;
    }

    private function parseSingleTimeInput($timeTracked, $hasMinutes, $hasHours, $isNegative)
    {
        if ($hasMinutes && $hasHours) {
            list($this->numHours, $this->numMinutes) = explode('h', $timeTracked);
            $this->numMinutes = trim(str_replace('m', '', $this->numMinutes));
        } else if ($hasMinutes && !$hasHours) {
            $pieces = explode('m', $timeTracked);
            $this->numMinutes = $pieces[0];
        } else if (!$hasMinutes && $hasHours) {
            $pieces = explode('h', $timeTracked);
            $this->numHours = $pieces[0];
        }

        $this->numMinutes = str_replace('-', '', $this->numMinutes);
        $this->numHours = str_replace('-', '', $this->numHours);

        if ($isNegative) {
            $this->numMinutes *= -1;
            $this->numHours *= -1;
        }
        return true;
    }

    private function parseRange($timeTracked)
    {
        $pieces = explode('-', $timeTracked);
        $from = trim($pieces[0]);
        $till = trim($pieces[1]);

        if ($from > 24 || $from < 0 || $till > 24 || $till < 0 || $from == $till) {
            return false;
        }

        if ($from > $till) {
            $this->numHours = 24 - $from + $till;
        } else {
            $this->numHours = $till - $from;
        }
        return true;
    }

    private function createResponsePanel($success)
    {
        $severity = $success ? PHUIInfoView::SEVERITY_SUCCESS : PHUIInfoView::SEVERITY_ERROR;
        $responseText = '';
        if ($success) {
            $responseText = 'Successfully tracked';
            if ($this->numHours != 0) {
                $responseText .= ' ' . $this->numHours . ' hours';
            }
            if ($this->numMinutes != 0) {
                $responseText .= ' ' . $this->numMinutes . ' minutes';
            }
            $responseText .= ' on project: ' . $this->projectName;
        } else {
            $responseText = 'Incorrect input';
        }

        $view = new PHUIInfoView();
        $view->setSeverity($severity);
        $view->setErrors(array(pht($responseText)));
        return $view;
    }

    private function createFeedbackMessage()
    {
        $severity = PHUIInfoView::SEVERITY_ERROR;
        $responseText = 'Please select project';
        $view = new PHUIInfoView();
        $view->setSeverity($severity);
        $view->setErrors(array(pht($responseText)));
        return $view;
    }

    private function tryGetProject($request){
        $projectPHID = null;
        try {
            $arr = $request->getRequestData()['add_project'];
            $projectPHID = end($arr);
        } catch (Exception $e) {

        }
        return $projectPHID;
    }
    public function getResponsePanel()
    {
        return $this->responsePanel;
    }

    public function getRequest()
    {
        return $this->request;
    }
}

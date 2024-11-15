<?php

class ManagementPanelUserNameBox {
    private $userID;
    
    public function __construct($userID) {
        $this->userID = $userID;
    }

    public function getBox() {
        $userNameSelected = ManagementPanelStorageManager::getUserName($this->userID);
        $view = new PHUIInfoView();
        $view->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
        $view->setErrors(
            array(
                phutil_safe_html('User name: <b>' . $userNameSelected . '</b>'),
            ));
        return $view;
    }
}
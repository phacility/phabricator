<?php

class ManagementPanelUserRequestInfo {
    
    private $request;
    
    public function __construct($request) {
        $this->request = $request;
    }
    
    public function getUserID(){
        $selectUser = $this->request->getRequestData();
        $userIDSelected = -1;

        try{
            $userIDSelected = ManagementPanelStorageManager::getUserID($selectUser['add_person'][0]);
            return $userIDSelected;
        }
        catch(Exception $e){

        }
       
        return $userIDSelected;
    }
}

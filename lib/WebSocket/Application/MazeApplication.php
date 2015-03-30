<?php

namespace WebSocket\Application;

class MazeApplication extends Application {

// These global data used to assign a colour tag to client
    private static $colourNamesArray = array('red', 'green', 'blue', 'aqua',
        'fuschia', 'lime', 'maroon', 'navy', 'yellow');
    private static $colourcounter = 0;
    private $_clients = array();

    public function onConnect($client) {

        $id = $client->getClientId();
        $this->_clients[$id] = $client;

        // Extra code to set up application specific client data
        $info = array();
        $info['colour'] = MazeApplication::$colourNamesArray[MazeApplication::$colourcounter];

        MazeApplication::$colourcounter++;
        if (MazeApplication::$colourcounter == 9)
            MazeApplication::$colourcounter = 0;
        $client->setClientInfo($info);
    }

    public function onDisconnect($client) {
        $id = $client->getClientId();
        unset($this->_clients[$id]);
    }

    public function onData($data, $client) {

        $decodedData = $this->_decodeData($data);
        // if ($decodedData === false) {
        // @todo: invalid request trigger error...
        // }
//        $actionName = '_action' . ucfirst($decodedData['action']);
//        if (method_exists($this, $actionName)) {
//            call_user_func(array($this, $actionName), array($decodedData['data']));
//        }
        $action = $decodedData['action'];
        $info = $decodedData['data'];
        $this->_process($client, $action, $info);
    }

//    private function _actionEcho($text) {
//        $encodedData = $this->_encodeData('echo', $text);
//        foreach ($this->_clients as $sendto) {
//            $sendto->send($encodedData);
//        }
//    }

    private function _process($client, $action, $info) {
        // Currently the only action defined is "Move"
        // First, update the client record
        $infoarray = $client->getClientInfo();
        $infoarray['uname'] = $info['uname'];
        $client->setClientInfo($infoarray);

        // Next, compose a data message block that contains details of all
        // clients
        $updateData = $this->_composeUpdateMessage();
        // Data block gets placed in a message struction with action
        // and data fields; only action for this application is 'update'
        $encodedUpdate = $this->_encodeData('login', $updateData);

        // Now send that to all connected clients
        foreach ($this->_clients as $sendto) {
            $sendto->send($encodedUpdate);
        }
    }

    private function _composeUpdateMessage() {
        $msgdata = array();
        foreach ($this->_clients as $aclient) {
            $info = $aclient->getClientInfo();
            $msgdata[] = $info;
        }
        return $msgdata;
    }

}

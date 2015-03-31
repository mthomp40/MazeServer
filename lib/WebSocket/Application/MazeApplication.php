<?php

namespace WebSocket\Application;

class MazeApplication extends Application {

// These global data used to assign a colour tag to client
    private static $colourNamesArray = array('red', 'green', 'blue', 'aqua',
        'fuschia', 'lime', 'maroon', 'navy', 'yellow');
    private static $colourcounter = 0;
    private static $mazemap = [// 1  2  3  4  5  6  7  8  9
        [1, 1, 1, 1, 1, 1, 1, 1, 1, 1,], // 0
        [1, 0, 1, 0, 0, 1, 0, 0, 0, 1,], // 1
        [1, 1, 1, 0, 0, 1, 0, 0, 0, 1,], // 2
        [1, 0, 1, 0, 0, 1, 1, 0, 0, 1,], // 3
        [1, 0, 1, 0, 0, 0, 1, 0, 0, 1,], // 4
        [1, 0, 1, 0, 0, 0, 1, 1, 1, 1,], // 5
        [1, 0, 1, 0, 0, 0, 0, 1, 0, 1,], // 6
        [1, 0, 1, 0, 0, 1, 0, 1, 0, 1,], // 7
        [1, 0, 1, 0, 0, 1, 0, 1, 0, 1,], // 8
        [1, 1, 1, 1, 1, 1, 1, 1, 1, 1,], // 9
    ];
    //private $mapW = $mazemap.length;
    //private $mapH = $mazemap[0].length;
    private $_clients = array();

    public function onConnect($client) {

        $id = $client->getClientId();
        $this->_clients[$id] = $client;

        // Extra code to set up application specific client data
        $info = array();
        $info['colour'] = MazeApplication::$colourNamesArray[MazeApplication::$colourcounter];

        MazeApplication::$colourcounter++;
        if (MazeApplication::$colourcounter == 9) {
            MazeApplication::$colourcounter = 0;
        }
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
        if ($action == "login") {
            foreach ($this->_clients as $aclient) {
                $infoarray = $aclient->getClientInfo();
                if (isset($infoarray['uname']) && $infoarray['uname'] === $info['uname']) {
                    $client->send($this->_encodeData('alreadyinuse', null));
                    return;
                }
            }
            $id = $client->getClientId();
            $this->_clients[$id] = $client;
            $infoarray = $client->getClientInfo();
            $infoarray['uname'] = $info['uname'];
            $infoarray['mazemap'] = json_encode(MazeApplication::$mazemap);
            $client->setClientInfo($infoarray);
            $updateData = $this->_composeUpdateMessage();
            $encodedUpdate = $this->_encodeData('setupmap', $updateData);
            $client->send($encodedUpdate);
        }
        $infoarray = $client->getClientInfo();
        $infoarray['uname'] = $info['uname'];
        $client->setClientInfo($infoarray);
        $updateData = $this->_composeUpdateMessage();
        $encodedUpdate = $this->_encodeData('update', $updateData);
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

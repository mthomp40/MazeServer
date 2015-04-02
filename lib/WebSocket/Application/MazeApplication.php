<?php

namespace WebSocket\Application;

class MazeApplication extends Application {

// These global data used to assign a colour tag to client
    private static $colourNamesArray = array('red', 'green', 'blue', 'aqua',
        'fuschia', 'lime', 'maroon', 'navy', 'yellow');
    private static $colourcounter = 0;
    private $_clients = array();
    private $maze;

    public function __construct() {
        $this->maze = new Maze(15, 15);
    }

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
        $infoarray['players'] = $this->_composeUpdateMessage();
        $encodedUpdate = $this->_encodeData('updateplayers', $infoarray['players']);
        foreach ($this->_clients as $sendto) {
            $sendto->send($encodedUpdate);
        }
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
            $infoarray = $client->getClientInfo();
            $infoarray['uname'] = $info['uname'];
            $infoarray['maze'] = $this->maze->cells;
            $client->setClientInfo($infoarray);
            $id = $client->getClientId();
            $this->_clients[$id] = $client;
            $encodedUpdate = $this->_encodeData('initgame', $infoarray);
            $client->send($encodedUpdate);
            $infoarray['players'] = $this->_composeUpdateMessage();
            $encodedUpdate = $this->_encodeData('updateplayers', $infoarray['players']);
            foreach ($this->_clients as $sendto) {
                $sendto->send($encodedUpdate);
            }
            return;
        } else if ($action == "start") {
            $infoarray = $client->getClientInfo();
            $infoarray['location'] = $this->maze->getStart();
            $infoarray['heading'] = "right";
            $infoarray['action'] = null;
            $client->setClientInfo($infoarray);
        } else if ($action == "direction") {
            $infoarray = $client->getClientInfo();
            $infoarray['heading'] = $info['heading'];
            $infoarray['action'] = null;
            $client->setClientInfo($infoarray);
            $id = $client->getClientId();
            $this->_clients[$id] = $client;
        } else if ($action == "fire") {
            $infoarray = $client->getClientInfo();
            $infoarray['heading'] = $info['heading'];
            $infoarray['action'] = "fire";
            $client->setClientInfo($infoarray);
            $id = $client->getClientId();
            $this->_clients[$id] = $client;
        } else if ($action == "move") {
            $infoarray = $client->getClientInfo();
            if ($info['heading'] === 'left') {
                --$infoarray['location']->x;
            } else if ($info['heading'] === 'right') {
                ++$infoarray['location']->x;
            } else if ($info['heading'] === 'up') {
                --$infoarray['location']->y;
            } else if ($info['heading'] === 'down') {
                ++$infoarray['location']->y;
            }
            $collision = $this->checkForCollision($infoarray['location']);
            if ($collision == null) {
                $infoarray['action'] = null;
                $client->setClientInfo($infoarray);
                $id = $client->getClientId();
                $this->_clients[$id] = $client;
            } else {
                $infoarray['action'] = null;
                $infoarray['player'] = $collision;
                $client->send($this->_encodeData('die', $infoarray));
                $infoarray['player'] = $client;
                $collision->send($this->_encodeData('die', $infoarray));
            }
        }
        $updateData = $this->_composeUpdateMessage();
        $encodedUpdate = $this->_encodeData('update', $updateData);
        foreach ($this->_clients as $sendto) {
            $sendto->send($encodedUpdate);
        }
    }

    private function checkForCollision($location) {
        foreach ($this->_clients as $aclient) {
            $info = $aclient->getClientInfo();
            if ($info['location'] == $location) {
                return $aclient;
            }
        }
        return null;
    }

    private function _composeUpdateMessage() {
        $msgdata = array();
        foreach ($this->_clients as $aclient) {
            $info = $aclient->getClientInfo();
            $msgdata[] = $info;
        }
        return $msgdata;
    }

    public function log($message, $type = 'info') {
        echo date('Y-m-d H:i:s') . ' [' . ($type ? $type : 'error') . '] ' . $message . PHP_EOL;
    }

}

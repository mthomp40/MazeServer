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
        $action = $decodedData['action'];
        $info = $decodedData['data'];
        $this->_process($client, $action, $info);
    }

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
            $infoarray['colour'] = MazeApplication::$colourNamesArray[MazeApplication::$colourcounter];
            MazeApplication::$colourcounter++;
            if (MazeApplication::$colourcounter == 9) {
                MazeApplication::$colourcounter = 0;
            }
            $infoarray['loggedin'] = true;
            $infoarray['inplay'] = false;
            $infoarray['maze'] = $this->maze->cells;
            $client->setClientInfo($infoarray);
            $encodedUpdate = $this->_encodeData('initgame', $infoarray);
            $client->send($encodedUpdate);
            unset($infoarray['maze']);
            $id = $client->getClientId();
            $this->_clients[$id] = $client;
        } else if ($action == "start") {
            $infoarray = $client->getClientInfo();
            $infoarray['location'] = $this->maze->getStart();
            $infoarray['heading'] = "right";
            $infoarray['action'] = null;
            $infoarray['health'] = 10;
            $infoarray['inplay'] = true;
            $client->setClientInfo($infoarray);
            $id = $client->getClientId();
            $this->_clients[$id] = $client;
        } else if ($action == "direction") {
            $infoarray = $client->getClientInfo();
            $infoarray['heading'] = $info['heading'];
            $infoarray['action'] = null;
            $client->setClientInfo($infoarray);
            $id = $client->getClientId();
            $this->_clients[$id] = $client;
        } else if ($action == "fire") {
            $hit = $this->checkForHits($client);
            if ($hit != null) {
                
            }
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
            $infoarray = $sendto->getClientInfo();
            if ($infoarray['loggedin'] == true) {
                $sendto->send($encodedUpdate);
            }
        }
    }

    private function checkForHits($client) {
        $infoarray = $client->getClientInfo();
        $wallatt = 1;
        for ($i = 1; $i < 4; $i++) {
            $tempx = 0;
            $tempy = 0;
            if ($infoarray['heading'] === 'left') {
                $tempx = $infoarray['location']->x - $i;
                $tempy = $infoarray['location']->y;
            } else if ($infoarray['heading'] === 'right') {
                $tempx = $infoarray['location']->x + $i;
                $tempy = $infoarray['location']->y;
            } else if ($infoarray['heading'] === 'up') {
                $tempx = $infoarray['location']->x;
                $tempy = $infoarray['location']->y - $i;
            } else if ($infoarray['heading'] === 'down') {
                $tempx = $infoarray['location']->x;
                $tempy = $infoarray['location']->y + $i;
            }
            if ($this->maze->cells[$tempy][$tempx] == 1) {
                ++$wallatt;
            } else {
                foreach ($this->_clients as $aclient) {
                    $clientinfo = $aclient->getClientInfo();
                    if ($clientinfo['location'] == $infoarray['location']) {
                        $damage = 0;
                        if ($i == 1) {
                            $damage = 16 / $wallatt;
                        } else if ($i == 2) {
                            $damage = 8 / $wallatt;
                        } else if ($i == 3) {
                            $damage = 4 / $wallatt;
                        }
                        $infoarray['health'] += $clientinfo['health'];
                        $clientinfo['health'] -= $damage;
                        if ($clientinfo['health'] <= 0) {
                            $clientinfo['action'] = null;
                            $clientinfo['player'] = $client;
                            $clientinfo['inplay'] = false;
                            $clientinfo['message'] = "You were killed by " . $infoarray['uname'] . "!";
                            $client->send($this->_encodeData('die', $clientinfo));
                        }
                    }
                }
            }
        }
    }

    private function checkForCollision($location) {
        foreach ($this->_clients as $aclient) {
            $infoarray = $aclient->getClientInfo();
            if ($infoarray['inplay'] == true && $infoarray['location'] == $location) {
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

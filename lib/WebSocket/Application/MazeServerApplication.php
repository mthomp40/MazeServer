<?php

namespace WebSocket\Application;

class MazeServerApplication extends Application {

// These global data used to assign a colour tag to client
    private static $colourNamesArray = array('red', 'green', 'blue', 'aqua',
        'fuschia', 'lime', 'maroon', 'navy', 'yellow');
    private static $colourcounter = 0;
    private $_clients = array();
    private $maze;

    public function __construct() {
        $this->maze = new Maze(50, 50);
    }

    public function onConnect($client) {
        $infoarray = $client->getClientInfo();
        $infoarray['inplay'] = false;
        $client->setClientInfo($infoarray);
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
            $infoarray['health'] = 0;
            $infoarray['maze'] = $this->maze->cells;
            $client->setClientInfo($infoarray);
            $encodedUpdate = $this->_encodeData('initgame', $infoarray);
            $client->send($encodedUpdate);
            unset($infoarray['maze']);
            $client->setClientInfo($infoarray);
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
        } else if ($action == "quit") {
            $infoarray = $client->getClientInfo();
            $infoarray['location'] = null;
            $infoarray['heading'] = null;
            $infoarray['action'] = null;
            $infoarray['health'] = 0;
            $infoarray['inplay'] = false;
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
            $infoarray = $client->getClientInfo();
            $infoarray['action'] = "fire";
            $infoarray['health'] = $this->checkForHits($client);
            $client->setClientInfo($infoarray);
            $id = $client->getClientId();
            $this->_clients[$id] = $client;
        } else if ($action == "move") {
            $infoarray = $client->getClientInfo();
            if ($info['heading'] === 'left') {
                if ($infoarray['location']->x === 0) {
                    $infoarray['location']->x = $this->maze->sizex - 1;
                } else {
                    --$infoarray['location']->x;
                }
            } else if ($info['heading'] === 'right') {
                if ($infoarray['location']->x === $this->maze->sizex - 1) {
                    $infoarray['location']->x = 0;
                } else {
                    ++$infoarray['location']->x;
                }
            } else if ($info['heading'] === 'up') {
                if ($infoarray['location']->y === 0) {
                    $infoarray['location']->y = $this->maze->sizey - 1;
                } else {
                    --$infoarray['location']->y;
                }
            } else if ($info['heading'] === 'down') {
                if ($infoarray['location']->y === $this->maze->sizey - 1) {
                    $infoarray['location']->y = 0;
                } else {
                    ++$infoarray['location']->y;
                }
            }
            $collision = $this->checkForCollision($infoarray);
            if ($collision == null) {
                $infoarray['action'] = null;
                $client->setClientInfo($infoarray);
                $id = $client->getClientId();
                $this->_clients[$id] = $client;
            } else {
                $collisioninfo = $collision->getClientInfo();
                $infoarray['message'] = "You died because you hit " . $collisioninfo['uname'] . "!";
                $infoarray['location'] = null;
                $infoarray['heading'] = null;
                $infoarray['action'] = null;
                $infoarray['health'] = 0;
                $infoarray['inplay'] = false;
                $client->setClientInfo($infoarray);
                $client->send($this->_encodeData('die', $infoarray));
                $id = $client->getClientId();
                $this->_clients[$id] = $client;
                $collisioninfo['message'] = "You died because " . $infoarray['uname'] . " hit you!";
                $collisioninfo['location'] = null;
                $collisioninfo['heading'] = null;
                $collisioninfo['action'] = null;
                $collisioninfo['health'] = 0;
                $collisioninfo['inplay'] = false;
                $collision->setClientInfo($collisioninfo);
                $collision->send($this->_encodeData('die', $collisioninfo));
                $id = $collision->getClientId();
                $this->_clients[$id] = $collision;
            }
        }
        $updateData = $this->_composeUpdateMessage();
        $encodedUpdate = $this->_encodeData('update', $updateData);
        foreach ($this->_clients as $sendto) {
            $infoarray = $sendto->getClientInfo();
            if (isset($infoarray['loggedin']) && $infoarray['loggedin'] == true) {
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
            if ($tempx < 0 || $tempx >= $this->maze->sizex || $tempy < 0 || $tempy >= $this->maze->sizey) {
                continue;
            }
            if ($this->maze->cells[$tempy][$tempx] == "1") {
                ++$wallatt;
            } else {
                foreach ($this->_clients as $aclient) {
                    $clientinfo = $aclient->getClientInfo();
                    if ($infoarray['uname'] != $clientinfo['uname'] && $clientinfo['inplay'] == true &&
                            $clientinfo['location']->x == $tempx && $clientinfo['location']->y == $tempy) {
                        $damage = 0;
                        if ($i == 1) {
                            $damage = 16 / $wallatt;
                        } else if ($i == 2) {
                            $damage = 8 / $wallatt;
                        } else if ($i == 3) {
                            $damage = 4 / $wallatt;
                        }
                        $infoarray['health'] += $clientinfo['health'];
                        $client->setClientInfo($infoarray);
                        $id = $client->getClientId();
                        $this->_clients[$id] = $client;

                        $clientinfo['health'] -= $damage;
                        $aclient->setClientInfo($clientinfo);
                        $id = $aclient->getClientId();
                        $this->_clients[$id] = $aclient;
                        if ($clientinfo['health'] <= 0) {
                            $clientinfo['location'] = null;
                            $clientinfo['heading'] = null;
                            $clientinfo['action'] = null;
                            $clientinfo['health'] = 0;
                            $clientinfo['inplay'] = false;
                            $clientinfo['message'] = "You were shot by " . $infoarray['uname'] . "!";
                            $aclient->setClientInfo($clientinfo);
                            $aclient->send($this->_encodeData('die', $clientinfo));
                            $id = $aclient->getClientId();
                            $this->_clients[$id] = $aclient;
                        }
                    }
                }
            }
        }
        return $infoarray['health'];
    }

    private function checkForCollision($client) {
        foreach ($this->_clients as $otherclient) {
            $infoarray = $otherclient->getClientInfo();
            if ($client['uname'] != $infoarray['uname'] && $infoarray['inplay'] == true &&
                    $infoarray['location'] == $client['location']) {
                return $otherclient;
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
}

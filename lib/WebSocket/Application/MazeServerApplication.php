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
        $this->maze = new Maze(20, 20);
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
            $infoarray['colour'] = MazeServerApplication::$colourNamesArray[MazeServerApplication::$colourcounter];
            MazeServerApplication::$colourcounter++;
            if (MazeServerApplication::$colourcounter == 9) {
                MazeServerApplication::$colourcounter = 0;
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
            $this->log($infoarray['uname'] . " logged in successfully");
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
            $this->log($infoarray['uname'] . " has started the game");
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
            $this->log($infoarray['uname'] . " has quit the game");
        } else if ($action == "direction") {
            $infoarray = $client->getClientInfo();
            $infoarray['heading'] = $info['heading'];
            $infoarray['action'] = null;
            $client->setClientInfo($infoarray);
            $id = $client->getClientId();
            $this->_clients[$id] = $client;
            $this->log($infoarray['uname'] . " is now heading in the " . $infoarray['heading'] . " direction");
        } else if ($action == "fire") {
            $infoarray = $client->getClientInfo();
            $infoarray['action'] = "fire";
            $infoarray['health'] = $this->checkForHits($client);
            $client->setClientInfo($infoarray);
            $id = $client->getClientId();
            $this->_clients[$id] = $client;
            $this->log($infoarray['uname'] . " just fired!");
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
                $this->log($infoarray['uname'] . " just moved " . $infoarray['heading'] . "wards");
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
                $this->log($infoarray['uname'] . " just collided with " . $collision['uname'] . "'!");
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
                $this->log("That's a wall, damage lessened :(");
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
                        $cost = $clientinfo['health'];
                        $infoarray['health'] += $clientinfo['health'];
                        $client->setClientInfo($infoarray);
                        $id = $client->getClientId();
                        $this->_clients[$id] = $client;

                        $clientinfo['health'] -= $damage;
                        $aclient->setClientInfo($clientinfo);
                        $id = $aclient->getClientId();
                        $this->_clients[$id] = $aclient;
                        $this->log($infoarray['uname'] . " just hit " . $clientinfo['uname'] . " and took " . $cost . " points off!");
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

    public function log($message, $type = 'info') {
        echo date('Y-m-d H:i:s') . ' [' . ($type ? $type : 'error') . '] ' . $message . PHP_EOL;
    }

}

/**
 * php5-easy-maze, a class to generate and solve mazes with HTML and text output
 * 
 * Copyright (c) 2011 david chan dchan@sigilsoftware.com
 * 
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 *
 */
class Point {

    public $x;
    public $y;

    public function __construct($x, $y) {
        $this->x = $x;
        $this->y = $y;
    }

}

class Maze {

    const WALL = '1';
    const EMPTY_PATH = '0';
    const START = 's';

    public $cells = array();
    public $sizex;
    public $sizey;
    private $visited = array();
    private $start = null;

    public function __construct($sizex = 51, $sizey = null) {
        if ($sizex % 2 == 0) {
            $sizex++;
        }
        if ($sizey === null) {
            $sizey = $sizex;
        } else if ($sizey % 2 == 0) {
            $sizey++;
        }
        $this->sizex = $sizex;
        $this->sizey = $sizey;

        for ($i = 0; $i < $sizey; $i++) {
            $this->cells[$i] = array_fill(0, $sizex, self::WALL);
            $this->visited[$i] = array_fill(0, $sizex, false);
        }

        $this->gen_depth_first(1, 1);

        //add break in walls
        for ($i = 1; $i < $sizey;) {
            if ($this->cells[$i][1] == self::EMPTY_PATH && $this->cells[$i][$sizex - 2] == self::EMPTY_PATH) {
                $this->cells[$i][0] = self::EMPTY_PATH;
                $this->cells[$i][$sizex - 1] = self::EMPTY_PATH;
            }
            $i += rand(2, 6);
        }

        for ($i = 1; $i < $sizey;) {
            $x = rand(2, $sizex - 2);
            $y = rand(2, $sizey - 2);
            if (($this->cells[$y][$x - 1] == self::WALL && $this->cells[$y][$x + 1] == self::WALL) ||
                    ($this->cells[$y - 1][$x] == self::WALL && $this->cells[$y + 1][$x] == self::WALL)) {
                $this->cells[$y][$x] = self::EMPTY_PATH;
                $i++;
            }
        }
    }

    private function randomPoint() {
        $p = new Point(rand(2, count($this->cells) - 2), rand(2, count($this->cells[1]) - 2));
        if ($this->cells[$p->x][$p->y] != self::EMPTY_PATH) {
            $p = $this->randomPoint();
        }
        return $p;
    }

    private function randomEdgePoint() {
        switch (rand(0, 3)) {
            case 0:
                // left
                $p = new Point(2, rand(1, count($this->cells[1]) - 2));
                break;
            case 1:
                // right
                $p = new Point(count($this->cells) - 2, rand(1, count($this->cells[1]) - 2));
                break;
            case 2:
                // top
                $p = new Point(rand(2, count($this->cells) - 2), 0);
                break;
            case 3:
                // bottom
                $p = new Point(rand(2, count($this->cells) - 2), count($this->cells[1]) - 2);
                break;
        }
        if ($this->cells[$p->x][$p->y] != self::EMPTY_PATH) {
            $p = $this->randomEdgePoint();
        }
        return $p;
    }

    private function gen_depth_first($x, $y) {
        $this->visited[$x][$y] = true;
        $this->cells[$x][$y] = self::EMPTY_PATH;

        $neighbors = $this->getNeighbors(new Point($x, $y), 2);
        shuffle($neighbors);
        foreach ($neighbors as $direction => $n) {
            if ($this->cells[$n->x][$n->y] === self::WALL) {
                $this->cells[($x + $n->x) / 2][($y + $n->y) / 2] = self::EMPTY_PATH;
                $this->gen_depth_first($n->x, $n->y);
            }
        }
    }

    protected function getNeighbors(Point $p, $step = 1) {
        $neighbors = array();

        if (array_key_exists($p->x - $step, $this->cells)) {
            $neighbors['n'] = new Point($p->x - $step, $p->y);
        }
        if (array_key_exists($p->x + $step, $this->cells)) {
            $neighbors['s'] = new Point($p->x + $step, $p->y);
        }
        if (array_key_exists($p->y + $step, $this->cells[$p->x])) {
            $neighbors['e'] = new Point($p->x, $p->y + $step);
        }
        if (array_key_exists($p->y - $step, $this->cells[$p->x])) {
            $neighbors['w'] = new Point($p->x, $p->y - $step);
        }
        return $neighbors;
    }

    public function getStart() {
        $this->setStart($this->randomPoint());
        return $this->start;
    }

    public function setStart(Point $start) {
        if ($this->start instanceof Point) {
            $this->cells[$this->start->x][$this->start->y] = self::EMPTY_PATH;
        }
        $this->start = $start;
        $this->cells[$this->start->x][$this->start->y] = self::START;
    }

    public function setCell(Point $p, $value) {
        if (
                $this->cells[$p->x][$p->y] != self::WALL && $this->cells[$p->x][$p->y] != self::START
        ) {
            $this->cells[$p->x][$p->y] = $value;
        }
        return $this->cells[$p->x][$p->y];
    }

    public function getCell(Point $p) {
        if (array_key_exists($p->x, $this->cells) && array_key_exists($p->y, $this->cells[$p->x])) {
            return $this->cells[$p->x][$p->y];
        }
        return false;
    }

    public function getCells() {
        return (array) $this->cells;
    }

}

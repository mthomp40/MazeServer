<?php

namespace WebSocket\Application;

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

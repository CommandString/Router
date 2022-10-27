<?php

namespace CommandString\Router;

use RuntimeException;
use stdClass;

final class Environment {
    private object $env;
    private static self $instance;

    public function __construct(string|bool $env_location = "./env.json")
    {
        if ($env_location === false) {
            $this->env = new stdClass();
        } else {
            if (!file_exists($env_location)) {
                throw new RuntimeException("$env_location is not a real file!");
            }

            try {
                $this->env = json_decode(file_get_contents($env_location));
            } catch (\TypeError $e) {
                throw new RuntimeException("$env_location is not a valid json file!");
            }
        }

        self::$instance = $this;
    }

    public function __get($name) {
        return $this->env->$name;
    }

    public function __set($name, $value) {
        if (isset($this->env->$name)) {
            return;
        }

        $this->env->$name = $value;
    }

    public static function get(): self
    {
        return self::$instance;
    }
}
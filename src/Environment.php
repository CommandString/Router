<?php

namespace CommandString\Router;

use RuntimeException;
use stdClass;

final class Environment {
    /**
     * @var object Holds environment variables
     */
    private object $env;

    /**
     * @var self Holds current instance of the class
     */
    private static self $instance;

    /**
     * @param string|bool $env_location
     */
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

    /**
     * @param string $name
     */
    public function __get($name) {
        return $this->env->$name ?? null;
    }

    /**
     * @param string $name
     * @param string $value
     */
    public function __set($name, $value) {
        if (isset($this->env->$name)) {
            return;
        }

        $this->env->$name = $value;
    }

    /**
     * @return self
     */
    public static function get(): self
    {
        return self::$instance;
    }
}

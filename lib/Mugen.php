<?php

namespace mugenreq;

use mugenreq\MugenSoap;
use mugenreq\TADZKLib;
use mugenreq\exceptions\ConnectionError;
use mugenreq\exceptions\UnrecognizedArgument;
use mugenreq\exceptions\UnrecognizedCommand;

class Mugen
{
    static private $parseable_args = [
        'com_key', 'pin', 'time', 'template',
        'name', 'password', 'group', 'privilege',
        'card', 'pin2', 'tz1', 'tz2', 'tz3',
        'finger_id', 'option_name', 'option_value', 'date',
        'size', 'valid', 'value', 'time'
    ];

    private $ip;

    private $internal_id;

    private $com_key;

    private $connection_timeout;

    private $encoding;

    private $udp_port;

    private $zklib;


    public static function commands_available()
    {
        return array_merge(static::soap_commands_available(), static::zklib_commands_available());
    }


    public static function soap_commands_available(array $options = [])
    {
        return MugenSoap::get_commands_available($options);
    }


    public static function zklib_commands_available()
    {
        return TADZKLib::get_commands_available();
    }


    public static function get_valid_commands_args()
    {
        return self::$parseable_args;
    }


    public static function is_device_online($ip, $timeout = 1)
    {
        $ch = curl_init($ip);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return (boolean) $response;
    }


    public function __construct(MugenSoap $soap_provider, TADZKLib $zklib_provider, array $options = [])
    {
        $this->ip = $options['ip'];
        $this->internal_id = (integer) $options['internal_id'];
        $this->com_key = (integer) $options['com_key'];
        $this->connection_timeout = (integer) $options['connection_timeout'];
        $this->encoding = strtolower($options['encoding']);
        $this->udp_port = (integer) $options['udp_port'];

        $this->zklib = $zklib_provider;
        $this->mugen_soap = $soap_provider;
    }


    public function __call($command, array $args)
    {
        $command_args = count($args) === 0 ? [] : array_shift($args);
        $this->check_for_connection() &&
        $this->check_for_valid_command($command) &&
        $this->check_for_unrecognized_args($command_args);
        
        if (in_array($command, MugenSoap::get_commands_available())) {
            $response = $this->execute_command_via_mugen_soap($command, $command_args);
        } else {
            $response = $this->execute_command_via_zklib($command, $command_args);
        }

        $this->check_for_refresh_mugen_db($command);
        return $response;
    }


    public function execute_command_via_mugen_soap($command, array $args = [])
    {
        $command_args = $this->config_array_items(array_merge(['com_key' => $this->get_com_key()], $args));
        return $this->mugen_soap->execute($command, $command_args, $this->encoding);
    }

    public function execute_command_via_zklib($command, array $args = [])
    {
        $command_args = $this->config_array_items($args);
        $response = $this->zklib->{$command}(array_merge(['encoding'=>$this->encoding], $command_args));

        return $response;
    }


    public function get_ip()
    {
        return $this->ip;
    }


    public function get_com_key()
    {
        return $this->com_key;
    }


    public function get_connection_timeout()
    {
        return $this->connection_timeout;
    }


    public function get_encoding()
    {
        return $this->encoding;
    }


    public function is_alive()
    {
        return static::is_device_online($this->get_ip(), $this->connection_timeout);
    }


    private function check_for_connection()
    {
        if (!$this->is_alive()) {
            throw new ConnectionError('Impossible to start connection with device ' . $this->get_ip());
        }
        return true;
    }


    private function check_for_valid_command($command)
    {
        $mugen_commands = static::commands_available();
        if (!in_array($command, $mugen_commands)) {
            throw new UnrecognizedCommand("Command $command not recognized!");
        }
        return true;
    }


    private function check_for_unrecognized_args(array $args)
    {
        if (0 !== count($unrecognized_args = array_diff(array_keys($args), static::get_valid_commands_args()))) {
            throw new UnrecognizedArgument('Unknown parameter(s): ' . join(', ', $unrecognized_args));
        }
        return true;
    }


    private function check_for_refresh_mugen_db($command_executed)
    {
        preg_match('/^(set_|delete_)/', $command_executed) && $this->execute_command_via_mugen_soap('refresh_db', []);
    }

    
    private function config_array_items(array $values)
    {
        $normalized_args = [];
        foreach (static::get_valid_commands_args() as $parseable_arg_key) {
            $normalized_args[$parseable_arg_key] =
                    isset($values[$parseable_arg_key]) ? $values[$parseable_arg_key] : null;
        }
        return $normalized_args;
    }
}
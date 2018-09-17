<?php

namespace mugenreq;

use mugenreq\Mugen;
use mugenreq\MugenSoap;


class MugenFactory
{
    private $options;


    public function __construct(array $options = [])
    {
        $this->options  = $options;
    }


    public function get_instance()
    {
        $options = $this->options;
        $this->set_options($this->get_default_options(), $options);
        
        $soap_options = [
            'location' => "http://{$options['ip']}/iWsService",
            'port' => 80,
            'connection_timeout' => $options['connection_timeout']
        ];

        return new Mugen(
            new MugenSoap($soap_options),
            $options
        );
    }


    private function get_default_options()
    {
        $default_options['ip'] = '10.50.2.10';
        $default_options['com_key'] = 111111;
        $default_options['encoding'] = 'iso8859-1';
        $default_options['connection_timeout'] = 5;
        return $default_options;
    }


    private function set_options(array $base_options, array &$options)
    {
        foreach ($base_options as $key => $default) {
            !isset($options[$key]) ? $options[$key] = $default : null;
        }
    }
}
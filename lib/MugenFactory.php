<?php

namespace mugenreq;

use mugenreq\Mugen;
use mugenreq\MugenSoap;
use mugenreq\TADZKLib;


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
            'connection_timeout' => $options['connection_timeout'],
            'soap_port' => $options['soap_port']
        ];

        return new Mugen(
            new MugenSoap($soap_options),
            new TADZKLib($options),
            $options
        );
    }


    private function get_default_options()
    {
        $default_options['ip'] = '127.0.0.1';
        $default_options['internal_id'] = 1;
        $default_options['com_key'] = 123456;
        $default_options['encoding'] = 'iso8859-1';
        $default_options['connection_timeout'] = 5;
        $default_options['soap_port'] = 80;
        $default_options['udp_port'] = 4370;

        return $default_options;
    }


    private function set_options(array $base_options, array &$options)
    {
        foreach ($base_options as $key => $default) {
            !isset($options[$key]) ? $options[$key] = $default : null;
        }
    }
}
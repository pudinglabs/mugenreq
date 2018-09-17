<?php

namespace mugenreq;

use mugenreq\MugenResponse;


class MugenSoap
{
    const XML_FAIL_RESPONSE = 'Fail!';
    const XML_SUCCESS_RESPONSE = 'Succeed!';

    private $soap_options;

    static private $commands_available = [
        'get_all_user_info'   => '<GetAllUserInfo><ArgComKey xsi:type="xsd:integer">%com_key%</ArgComKey></GetAllUserInfo>',
    ];


    public function __construct(array $soap_options)
    {
        $this->soap_options = $soap_options;
    }

    static public function get_commands_available(array $options = [])
    {
        return (isset($options['include_command_string']) && $options['include_command_string']) ? self::$commands_available : array_keys(self::$commands_available);
    }

    public function execute($command, array $args, $encoding)
    {
        $request = $this->build_request($command, $args, $encoding);
        $response = $this->execute_request($request);
        return new MugenResponse($response, $encoding);
    }


    public function get_soap_options()
    {
        return $this->soap_options;
    }


    public function build_request($command, array $args, $encoding)
    {
        $command_string = $this->get_command_string($command);
        $request = $this->parse_command_string($command_string, $args);
        $request = $this->normalize_xml_string($request, $encoding);
        return $request;
    }

    private function execute_request($request)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->get_soap_options()['location']);
        curl_setopt($ch, CURLOPT_PORT, $this->get_soap_options()['port']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->get_soap_options()['connection_timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml', 'Content-Length: '.strlen($request)));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    private function get_command_string($command)
    {
        return self::$commands_available[$command];
    }

    private function parse_command_string($command_string, array $args)
    {
        $parseable_args = array_map(
            function($item) {
                return '%' . $item . '%';
            },
            array_keys($args)
        );
        $parsed_command = str_replace($parseable_args, array_values($args), $command_string);
        return $parsed_command;
    }

    public static function normalize_xml_string($xml, $encoding = 'utf-8')
    {
        $xml ='<?xml version="1.0" encoding="' . $encoding . '" standalone="no"?>' . $xml;
        return trim(str_replace([ "\n", "\r" ], '', $xml));
    }
}
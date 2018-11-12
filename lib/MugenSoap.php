<?php

namespace mugenreq;

use mugenreq\MugenResponse;


class MugenSoap
{
    const XML_FAIL_RESPONSE = 'Fail!';
    const XML_SUCCESS_RESPONSE = 'Succeed!';

    private $soap_options;

    static private $commands_available = [
        'get_date_machine'    => '<GetDate><ArgComKey>%com_key%</ArgComKey></GetDate>',
        'get_att_log'         => '<GetAttLog><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></GetAttLog>',
        'get_user_info'       => '<GetUserInfo><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></GetUserInfo>',
        'get_all_user_info'   => '<GetAllUserInfo><ArgComKey>%com_key%</ArgComKey></GetAllUserInfo>',
        'get_user_template'   => '<GetUserTemplate><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN><FingerID>%finger_id%</FingerID></Arg></GetUserTemplate>',
        'get_option_machine'  => '<GetOption><ArgComKey>%com_key%</ArgComKey><Arg><Name>%option_name%</Name></Arg></GetOption>',
        'set_user_info'       => ['<DeleteUser><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></DeleteUser>', '<SetUserInfo><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN><Name>%name%</Name><Password>%password%</Password><Group>%group%</Group><Privilege>%privilege%</Privilege><Card>%card%</Card><PIN2>%pin%</PIN2><TZ1>%tz1%</TZ1><TZ2>%tz2%</TZ2><TZ3>%tz3%</TZ3></Arg></SetUserInfo>'],
        'set_user_template'   => '<SetUserTemplate><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN><FingerID>%finger_id%</FingerID><Size>%size%</Size><Valid>%valid%</Valid><Template>%template%</Template></Arg></SetUserTemplate>',
        'set_option_machine'  => '<SetOption><ArgComKey>%com_key%</ArgComKey><Arg><Name>%option_name%</Name><Value>%option_value%</Value></Arg></SetOption>',
        'set_date_machine'    => '<SetDate><ArgComKey>%com_key%</ArgComKey><Arg><Date>%date%</Date><Time>%time%</Time></Arg></SetDate>',
        'delete_user'         => '<DeleteUser><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></DeleteUser>',
        'delete_template'     => '<DeleteTemplate><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></DeleteTemplate>',
        'delete_user_password'=> '<ClearUserPassword><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></ClearUserPassword>',
        'delete_data'         => '<ClearData><ArgComKey>%com_key%</ArgComKey><Arg><Value>%value%</Value></Arg></ClearData>',
        'refresh_db'          => '<RefreshDB><ArgComKey>%com_key%</ArgComKey></RefreshDB>',
        'restart_machine'     => '<Restart><ArgComKey>%com_key%</ArgComKey></Restart>',
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
        $response = !is_array($request) ? $this->execute_request($request) : $this->execute_multiple_requests($request);
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

        if (!is_array($request)) {
            $request = $this->normalize_xml_string($request, $encoding);
        } else {
            $request = array_map(
                function ($request) use ($encoding) {
                    return $this->normalize_xml_string($request, $encoding);
                },
                $request
            );
        }

        return $request;
    }

    private function execute_request($request)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->get_soap_options()['location']);
        curl_setopt($ch, CURLOPT_PORT, $this->get_soap_options()['soap_port']);
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

    private function execute_multiple_requests(array $requests)
    {
        foreach ($requests as $request) {
            $result = $this->execute_request($request);
        }

        return $result;
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
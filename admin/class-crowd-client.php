<?php

/**
 * The SOAP client to authenticate against Atlassian Crowd.
 *
 * @link       https://www.auderset.dev
 * @since      1.0.0
 *
 * @package    Crowd
 * @subpackage Crowd/admin
 */

/**
 * Atlassian Crowd SOAP Client
 *
 * Provides a SOAP client for to authenticate against Atlassian Crowd.
 *
 * @package    Crowd
 * @subpackage Crowd/admin
 * @author     Florian Auderset <florian@auderset.dev>
 */
class Crowd_Client
{

    /**
     * The soap client.
     *
     * @since    1.0.0
     * @access   private
     * @var      SoapClient $crowd_login_client The SOAP client.
     */
    private $crowd_login_client;

    /**
     * The soap client configuration.
     *
     * @since    1.0.0
     * @access   private
     * @var      array $crowd_login_configuration The SOAP client configuration.
     */
    private $crowd_login_configuration;

    /**
     * The soap client token.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $crowd_login_app_token The SOAP client token.
     */
    private $crowd_login_app_token;

    /**
     * Crowd_Client constructor.
     *
     * @throws Crowd_Connection_Exception
     */
    public function __construct()
    {
        $this->crowd_login_configuration = get_option('crowd_login_option_name');
        $service_url = $this->crowd_login_configuration['crowd_url'] . '/services/' . 'SecurityServer?wsdl';

        try {
            $this->crowd_login_client = new SoapClient($service_url);
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            throw new Crowd_Connection_Exception($message, $code);
        }
    }

    /**
     * Authenticates application against Atlassian Crowd server.
     *
     * @return string
     * @throws Crowd_Login_Exception
     */
    public function authenticateApplication()
    {
        $params = [
            'in0' => [
                'credential' => [
                    'credential' => $this->crowd_login_configuration['crowd_application_password']
                ],
                'name' => $this->crowd_login_configuration['crowd_application_name']
            ]
        ];

        try {
            $response = $this->crowd_login_client->authenticateApplication($params);
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            echo "SOAP Fault: faultcode: {$code}, faultstring: {$message}";
        }

        $this->crowd_login_app_token = $response->out->token;

        if (empty($this->crowd_login_app_token)) {
            throw new Crowd_Login_Exception("Unable to login to Crowd. Please check your credentials.");
        } else {
            return $this->crowd_login_app_token;
        }
    }

    /**
     * Authenticates a principal to the Crowd security server for the application client.
     */
    public function authenticatePrincipal($name, $credential, $user_agent, $remote_address)
    {
        $params = [
            'in0' => [
                'name' => $this->crowd_login_configuration['crowd_application_name'],
                'token' => $this->crowd_login_app_token
            ],
            'in1' => [
                'application' => $this->crowd_login_configuration['crowd_application_name'],
                'credential' => ['credential' => $credential],
                'name' => $name,
                'validationFactors' => [
                    [
                        'name' => 'User-Agent',
                        'value' => $user_agent
                    ],
                    [
                        'name' => 'remote_address',
                        'value' => $remote_address
                    ]
                ]
            ]
        ];

        try {
            $response = $this->crowd_login_client->authenticatePrincipal($params);
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            echo "SOAP Fault: faultcode: {$code}, faultstring: {$message}";
            return null;
        }

        $princ_token = $response->out;

        return $princ_token;
    }

    /**
     * Determines if the principal's current token is still valid in Crowd.
     */
    public function isValidPrincipalToken($princ_token, $user_agent, $remote_address)
    {
        $params = [
            'in0' => [
                'name' => $this->crowd_login_configuration['crowd_application_name'],
                'token' => $this->crowd_login_app_token
            ],
            'in1' => $princ_token,
            'in2' => [
                [
                    'name' => 'User-Agent',
                    'value' => $user_agent
                ],
                [
                    'name' => 'remote_address',
                    'value' => $remote_address
                ]
            ]
        ];

        try {
            $response = $this->crowd_login_client->isValidPrincipalToken($params);
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            echo "SOAP Fault: faultcode: {$code}, faultstring: {$message}";
            return '';
        }

        $valid_token = $response->out;

        return $valid_token;
    }

    /**
     * Invalidates a token for for this princpal for all application clients in Crowd.
     */
    public function invalidatePrincipalToken($princ_token)
    {
        $params = [
            'in0' => [
                'name' => $this->crowd_login_configuration['crowd_application_name'],
                'token' => $this->crowd_login_app_token
            ],
            'in1' => $princ_token
        ];

        try {
            $response = $this->crowd_login_client->invalidatePrincipalToken($params);
            return true;
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            echo "SOAP Fault: faultcode: {$code}, faultstring: {$message}";
        }
        return false;
    }

    /**
     * Finds a principal by token.
     */
    public function findPrincipalByToken($princ_token)
    {
        $params = [
            'in0' => [
                'name' => $this->crowd_login_configuration['crowd_application_name'],
                'token' => $this->crowd_login_app_token
            ],
            'in1' => $princ_token
        ];

        try {
            $response = $this->crowd_login_client->findPrincipalByToken($params);
            return $response->out;
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            echo "SOAP Fault: faultcode: {$code}, faultstring: {$message}";
            return null;
        }
    }

    /**
     * Finds all of the groups the specified principal is in.
     */
    public function findGroupMemberships($princ_name)
    {
        $params = [
            'in0' => [
                'name' => $this->crowd_login_configuration['crowd_application_name'],
                'token' => $this->crowd_login_app_token
            ],
            'in1' => $princ_name
        ];

        try {
            $response = $this->crowd_login_client->findGroupMemberships($params);
            return $response->out;
        } catch (SoapFault $soapFault) {
            $code = $soapFault->getCode();
            $message = $soapFault->getMessage();
            echo "SOAP Fault: faultcode: {$code}, faultstring: {$message}";
            return null;
        }
    }
}
<?php
namespace App\Libraries;

/**
 * KVM API manager
 */
class KvmManager {

    //Auth info
    private $_authInfo;
    private $_serverURI = '';

    private $_statusSuccess = true;
    private $_statusMessage = '';
    private $_resultList = [];

    //Sample OS Names
    private $_vmiList = [
        'ISPsystem__CentOS-7-amd64'             => 'CentOS-7-amd64',
        'ISPsystem__CentOS-8-Stream-amd64'      => 'CentOS-8-Stream-amd64',
        'ISPsystem__CentOS-8-amd64'             => 'CentOS-8-amd64',
        'ISPsystem__Debian-10-x86_64'           => 'Debian-10-x86_64',
        'ISPsystem__Debian-11-x86_64'           => 'Debian-11-x86_64',
        'ISPsystem__FreeBSD-12-amd64'           => 'FreeBSD-12-amd64',
        'ISPsystem__Ubuntu-16.04-amd64'         => 'Ubuntu-16.04-amd64',
        'ISPsystem__Ubuntu-20.04-amd64'         => 'Ubuntu-20.04-amd64',
    ];

    

    /**
     *  Constructor
     */
    function __construct($serverAddr, $port = 1500, $user = '', $password = '') {
        $this->_authInfo = '';
        $password = urlencode($password);
        $authURL = sprintf('https://%s:%s/vmmgr?out=xml&func=auth&username=%s&password=%s', $serverAddr, $port, $user, $password);

        //Do authorization
        if ($this->_doAuth($authURL)) {
            $this->_serverURI = sprintf('https://%s:%s/vmmgr?out=xml&auth=%s', $serverAddr, $port, $this->_authInfo);
        }
    }

    
    /**
     * Submit Action to the VM Server and get response from it.
     * @param String $actionURL: Your API URL with params
     */
    protected function _submitAction($actionURL) {

        $responseXml = "";

        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            // error was suppressed with the @-operator
            if (0 === error_reporting()) {
                return false;
            }

            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        try {

            $fHandler = fopen($actionURL, "r");
            if ($fHandler) {
                while($data = fread($fHandler, 4096)){
                    $responseXml .= $data;
                }
                fclose($fHandler);
            }
        }
        catch (\Exception $e) {
            $this->_statusSuccess = false;
            $this->_statusMessage = $e->getMessage();
        }
        finally {
            restore_error_handler();
        }

        if ($responseXml == '') {
            $this->_statusSuccess = false;
        }

        return $responseXml;
    }

    /**
     * Parse XML Response
     * @param String $responseXml: Response from API server
     */
    protected function _parseResponseXML($responseXml) {

        $this->_resultList = [];
        $this->_statusMessage = '';

        if ($this->_statusSuccess && $responseXml != '') {
            $doc = new \DOMDocument();
            if($doc->loadXML($responseXml)) {
                $root = $doc->documentElement;
                foreach ($root->childNodes as $elem) {
                    if (isset($elem->tagName)) {
                        
                        $this->_statusSuccess = true;

                        if ($elem->tagName == 'error') {
                            foreach ($elem->childNodes as $node) {
                                if (isset($node->tagName) && $node->tagName == 'msg') {
                                    $this->_statusMessage = $node->nodeValue;
                                    break;
                                }   
                            }
                            $this->_statusSuccess = false;
                            return false;
                        }
                        else if ($elem->tagName == 'auth') {
                            //Auth success
                            $this->_authInfo = $elem->nodeValue;
                            return true;
                        }
                        else if ($elem->tagName == 'elem') {

                            $resultData = [];
                            foreach ($elem->childNodes as $node) {
                                if (isset($node->tagName)) {
                                    $resultData[$node->tagName] = $node->nodeValue;
                                }
                            }
                            $this->_resultList[] = $resultData;
                            
                        }
                        else if ($elem->tagName == 'ok') {
                            //Auth success
                            return true;
                        }
                    }
                }           
            }
        }
        else {
            return false;
        }

        return true;
        
    }

    /**
     * Generate Action URL with Params
     * @param Array $params: Your api params
     */
    protected function _makeActionURL($params) {

        $actionURL = $this->_serverURI;

        $list = [];
        foreach ($params as $key => $val) {
            $list[] = sprintf("%s=%s", urlencode($key), urlencode($val));
        }
        if (count($list) > 0) {
            $actionURL .= '&' . implode('&', $list);
        }
        return $actionURL;

    }

    /**
     * Do Auth with credentials
     * @param String $authURL: Authentication URL
     */
    protected function _doAuth($authURL) {

        $this->_authInfo = '';
        $responseXml = $this->_submitAction($authURL);
        return $this->_parseResponseXML($responseXml);

    }

    /**
     * Get last status
     */
    public function getLastStatus() {
        return [
            'success'=> $this->_statusSuccess,
            'message'=> $this->_statusMessage,
        ];
    }

    /**
     * Get All VMs in the server
     */
    public function getVMList() {

        if (empty($this->_authInfo)) {
            return false;
        }

        $params = [
            'func' => 'vm'
        ];
        $actionURL = $this->_makeActionURL($params);
        $responseXml = $this->_submitAction($actionURL);

        $this->_parseResponseXML($responseXml);
        return $this->_resultList;

    }

    /**
     * It will restart VPS. You can restart multiple VMs
     * @param Array/String $vmIDs: VM ID list or VM ID
     */
    public function rebootVPS($vmIDs) {

        if (empty($this->_authInfo)) {
            return false;
        }

        if (empty($vmIDs)) {
            return false;
        }

        if (!is_array($vmIDs)) {
            $vmIDs = [$vmIDs];
        }

        $params = [
            'func' => 'vm.restart',
            'elid' => implode(', ', $vmIDs)
        ];
        $actionURL = $this->_makeActionURL($params);

        
        $responseXml = $this->_submitAction($actionURL);

        return $this->_parseResponseXML($responseXml);
    }

    /**
     * Change Password of VM
     * @param String $vmID: You can get the parameter from getVMList()
     * @param String $newPassword: Your new password
     */
    public function changePassword($vmID, $newPassword) {

        if (empty($this->_authInfo)) {
            return false;
        }

        $params = [
            'func' => 'vm.chpasswd',
            'elid' => $vmID,
            'password' => $newPassword,
            'confirm' => $newPassword,
            'sok' => 'ok'
        ];
        $actionURL = $this->_makeActionURL($params);

        $responseXml = $this->_submitAction($actionURL);
        return $this->_parseResponseXML($responseXml);

    }

    /**
     * Reinstall
     * @param String $vmID: VM ID
     * @param String $vmi: Your OS Version String
     * @param String $newPassword: password
     * @param String $sshPubKey: sshPubkey
     */
    public function osReinstall($vmID, $vmi, $newPassword, $sshPubKey) {

        if (empty($this->_authInfo)) {
            return false;
        }

        $params = [
            'func' => 'vm.reinstall',
            'elid' => $vmID,
            'vmi' => $vmi,
            //'recipe' => ,
            'osname' => isset($this->_vmiList[$vmi])? $this->_vmiList[$vmi]: '',
            //'new_password' => 'on', //If it is on, then you will be asked to set user name and password while installing.
            'password' => $newPassword,
            'confirm' => $newPassword,
            'sshpubkey' => $sshPubKey,
            'sok' => 'ok',
        ];

        $actionURL = $this->_makeActionURL($params);
        $responseXml = $this->_submitAction($actionURL);

        return $this->_parseResponseXML($responseXml);

    }
    
}
<?php
/*

Nordname Registrar Module 

*/
class Registrar_Adapter_Nordname extends Registrar_AdapterAbstract
{
    public $config = array(
        'apikey' => null,
    );
    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }
        if(isset($options['apikey']) && !empty($options['apikey'])) {
            $this->config['apikey'] = $options['apikey'];
            unset($options['apikey']);
        } else {
            throw new Registrar_Exception('Domain registrar "NordName" is not configured properly. Please update configuration parameter "NordName Apikey" at "Configuration -> Domain registration".');
        }
    }
    public static function getConfig()
    {
        return array(
            'label' => 'Manages domains on NordName via API',
            'form'  => array(
                'apikey' => array('password', array(
                    'label' => 'NordName API key',
                    'description'=>'NordName API key',
                    'renderPassword' => true,
                ),
                ),
        ),
		);
    }

    public function getTlds()
    {
        return array(
            '.com', '.net', '.org', '.pw', '.eu', '.info', '.me', '.biz', '.club', 
	    '.xyz', '.site', '.design', '.pro', '.cc', '.work', '.io', '.ltd', '.guru', 
	    '.city', '.es', '.in', '.ws', '.media', '.de', '.online'
        );
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );

        $result = $this->_request('checkRegistrationAvailability', $params);
        $status = $result["reply"][$domain->getName()]["status"];
        return ($status == "available");
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function modifyNs(Registrar_Domain $domain)
    {

        $nameservers = array ($domain->getNs1(), $domain->getNs2());
        if($domain->getNs3())  {
            array_push($nameservers, $domain->getNs3());
        }
        if($domain->getNs4())  {
            array_push($nameservers, $domain->getNs4());
        }
        
        $params = array(
            'domain' => $domain->getName(),
        );
        $nsString = implode(",", array_filter($nameservers));
        if (!empty($nsString))
            $params["nameservers"] = $nsString;

        $this->_request('changeNameservers', $params);

        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function modifyContact(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();

        $params = array(
            'domain' => $domain->getName(),
            'firstname' => $c->getFirstName(),
            'lastname' => $c->getLastName(),
            'address1' => $c->getAddress1(),
            'city' => $c->getCity(),
            'state' => $c->getState(),
            'zip' => $c->getZip(),
            'country' => $c->getCountry(),
            'email' => $c->getEmail(),
            'phone' => '+' . $c->getTelCc() . '.' . $c->getTel(),
        );
        
        if (!empty($c->getCompany()))
            $params["company"] = ($c->getCompany());
        
        if (!empty($c->getAddress2()))
            $params["address2"] = ($c->getAddress2());

        $this->_request('changeRegistrantInformation', $params);

        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function transferDomain(Registrar_Domain $domain)
    {
        $nameservers = array ($domain->getNs1(), $domain->getNs2());
        if($domain->getNs3())  {
            array_push($nameservers, $domain->getNs3());
        }
        if($domain->getNs4())  {
            array_push($nameservers, $domain->getNs4());
        }
        
        $c = $domain->getContactRegistrar();
        $params = array(
            'domain' => $domain->getName(),
            'authcode' => $domain->getEpp(),
            'firstname' => $c->getFirstName(),
            'lastname' => $c->getLastName(),
            'address1' => $c->getAddress1(),
            'city' => $c->getCity(),
            'state' => $c->getState(),
            'zip' => $c->getZip(),
            'country' => $c->getCountry(),
            'email' => $c->getEmail(),
            'phone' => '+' . $c->getTelCc() . '.' . $c->getTel(),
        );
        
        $nsString = implode(",", array_filter($nameservers));
        if (!empty($nsString))
            $params["nameservers"] = $nsString;
        
        if (!empty($c->getCompany()))
            $params["company"] = ($c->getCompany);
        
        if (!empty($c->getAddress2()))
            $params["address2"] = ($c->getAddress2());

        $this->_request('transferDomain', $params);
        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return Registrar_Domain
     * @throws Registrar_Exception
     */
    public function getDomainDetails(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );

        $result = $this->_request('getDomainInfo', $params);
        $result = $result["reply"];
        $contact = $result["registrant"];

        $c = new Registrar_Domain_Contact();
        $c->setFirstName((string) $contact["firstname"])
            ->setLastName((string) $contact["lastname"])
            ->setEmail((string) $contact["email"])
            ->setCompany((string) $contact["company"])
            ->setTel((string) $contact["phone"])
            ->setAddress1((string) $contact["address1"])
            ->setAddress2((string) $contact["address2"])
            ->setCity((string) $contact["city"])
            ->setCountry((string) $contact["country"])
            ->setState((string) $contact["state"])
            ->setZip((string) $contact["zip"]);
        // Add nameservers
        $i = 1;
        foreach ($result["nameservers"] as $ns)
        {
            if ($i == 1){
                $domain->setNs1($ns);
            }
            if ($i == 2){
                $domain->setNs2($ns);
            }
            if ($i == 3){
                $domain->setNs3($ns);
            }
            if ($i == 4){
                $domain->setNs4($ns);
            }
            $i++;
        }
        $privacy = false;
        if ((string) $result["privacy"] == 0)
            $privacy = true;

        $domain->setExpirationTime(strtotime($result["expires"]));
        $domain->setRegistrationTime(strtotime($result["registered"]));
        $domain->setPrivacyEnabled($privacy);
        //$domain->setEpp();
        $domain->setContactRegistrar($c);

        return $domain;

    }

    /**
     * @param Registrar_Domain $domain
     * @throws Registrar_Exception
     */
    public function deleteDomain(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Registrar does not support domain removal.');
    }
    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function registerDomain(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();

        $nameservers = array ($domain->getNs1(), $domain->getNs2());
        if($domain->getNs3())  {
            array_push($nameservers, $domain->getNs3());
        }
        if($domain->getNs4())  {
            array_push($nameservers, $domain->getNs4());
        }
        
        $params = array(
            'domain' => $domain->getName(),
            'years' => $domain->getRegistrationPeriod(),          

            'firstname' => $c->getFirstName(),
            'lastname' => $c->getLastName(),
            'address1' => $c->getAddress1(),
            'city' => $c->getCity(),
            'state' => $c->getState(),
            'zip' => $c->getZip(),
            'country' => $c->getCountry(),
            'email' => $c->getEmail(),
            'phone' => '+' . $c->getTelCc() . '.' . $c->getTel(),
        );
        
        $nsString = implode(",", array_filter($nameservers));
        if (!empty($nsString))
            $params["nameservers"] = $nsString;
        
        if (!empty($c->getCompany()))
            $params["company"] = ($c->getCompany);
        
        if (!empty($c->getAddress2()))
            $params["address2"] = ($c->getAddress2());

        /*if ($domain->getName() == '.us'){
            $params['usnc'] = 'C12';
            $params['usap'] = 'P3';
        }*/
        
        $result = $this->_request('registerDomain', $params);

        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function renewDomain(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
            'years' => $domain->getRegistrationPeriod(),
        );

        $this->_request('renewDomain', $params);
        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function togglePrivacyProtection(Registrar_Domain $domain)
    {
        $result = $this->_request('getDomainInfo', $params);
        $privacy = 0;
        if ((string) $result["reply"]["privacy"] == 0)
            $privacy = 1;
            
        $params = array(
            'domain' => $domain->getName(),
            'privacy' => $privacy,
        );

        $this->_request("changePrivacy", $params);
        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );

        $result = $this->_request('checkRegistrationAvailability', $params);
        $status = $result["reply"][$domain->getName()]["status"];
        return ($status == "unavailable");
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function lock(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
            'registrylock' => 1
        );

        $result = $this->_request('changeRegistryLock', $params);
        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function unlock(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
            'registrylock' => 0
        );

        $result = $this->_request('changeRegistryLock', $params);
        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
            'privacy' => 1
        );

        $result = $this->_request('changePrivacy', $params);
        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
            'privacy' => 0
        );

        $result = $this->_request('changePrivacy', $params);
        return true;
    }

    /**
     * @param Registrar_Domain $domain
     * @return bool
     * @throws Registrar_Exception
     */
    public function getEpp(Registrar_Domain $domain)
    {
        $params = array(
            'domain' => $domain->getName(),
        );

        $result = $this->_request('sendEPP', $params);
        return 'The EPP code of this domain has been emailed to the registrant email.';
    }
    /**
     * Runs an api command and returns parsed data.
     * @param string $cmd
     * @param array $params
     * @return array
     */
    private function _request($cmd, $params)
    {
        $params['api_key'] = $this->config['apikey'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_getApiUrl() . $cmd . "?" . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 360);


        $result = curl_exec($ch);

        if ($result === false) {
            $e = new Registrar_Exception(sprintf('CurlException: "%s"', curl_error($ch)));
            $this->getLog()->err($e);
            curl_close($ch);
            throw $e;
        }
        curl_close($ch);

        $this->getLog()->debug($this->_getApiUrl() . $cmd . '?' . http_build_query($params));
        $this->getLog()->debug(print_r($result, true));
        try {
            $json = json_decode($result, true);
        } catch (Exception $e) {
            throw new Registrar_Exception($e->getMessage());
        }

        if ($json["code"] != 300 && $json["code"] != 302)
            throw new Registrar_Exception($json["desc"]);

        return $json;
    }

    public function isTestEnv()
    {
        return $this->_testMode;
    }
    /**
     * Api URL.
     * @return string
     */
    private function _getApiUrl()
    {
        if ($this->isTestEnv())
            return 'https://c-soft.net/sandbox_api/v1/domain/';
        return 'https://c-soft.net/api/v1/domain/';
    }
}

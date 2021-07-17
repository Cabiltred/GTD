<?php

/**
 * Class Definicion de variables de configuracion
 *
 * Esta clase se definen todas las CONSTANTES que se van a utilizar
 * en la creación de los Webservices.
 *
 *                                  ╔═╗      ╔═╗
 *                                ╔═╝ ╚═╗    ║ ║
 *                          ╔════╗╚╗  ╔═╝ ╔══╝ ║
 *                          ║ ╔╗ ║ ║  ║   ║ ╔╗ ║
 *                          ║ ╚╝ ║ ║  ╚═╗ ║ ╚╝ ║
 *                          ╚═╗  ║ ╚════╝ ╚════╝
 *                          ╔═╝  ║
 *                          ╚════╝
 *
 * PHP Version: 5.5
 *
 * Contribution:
 * Refactor code by Alberto Chaves Beltran  <manuel.chaves@softek.com>
 *
 * @category WebService
 * @package ClasesTransversal
 * @author Macarena Cerda Mora      <mcerda@grupogtd.com>
 * @author Franciso Sandoval Iturra <Francisco.Sandoval@grupogtd.com>
 * @copyright 2020 GTD
 * @license https://opensource.org/licenses/MIT MIT License
 * @link http://localhost/
 *
 */

namespace Gtd\ClasesTransversal;

/**
 * Config
 */
class Config
{
    // Mediador
    public $dbServer;
    public $dbName;
    public $dbUser;
    public $dbPass;
    // Minerva
    public $dbServerMinerva;
    public $dbNameMinerva;
    public $dbUserMinerva;
    public $dbPassMinerva;
    // Cdk
    public $serverCdk;
    public $usernameCdk;
    public $passCdk;
    public $sessionCdk;
    public $portCdk;
    // Ws Mediador
    public $urlWsdl;
    public $folderWsdl;
    public $urlLog;
    public $sshAuthenticationUser;
    public $sshAuthenticationPass;
    public $sshHomeDirectory;
    public $ipDhcp;
    public $urlDhcp;
    public $dominioMinerva;
    // Ws Vo
    public $urlVo;
    public $portVo;
    public $urlApiVo;
    public $urlTokenVo;
    public $clientIdVo;
    public $clientSecretVo;
    public $typeOauthVo;
    public $tenantVo;
    public $elementTypeVo;
    public $technologyTvVo;
    public $provisionVo;
    public $serviceTypeVo;
    public $accessTypeVo;
    public $typeVo;
    public $serviceTypeVoCorp;
    public $prefixService;
    // General
    public $prefix;
    public $logIsFull;
    public $referer;

    /**
     * __construct
     *
     * @param  mixed $ambiente
     * @return void
     */
    public function __construct($ambiente="DEV")
    {
        switch($ambiente){
            case "DEV":
                $this->ambienteDesarrollo();
                break;
            case "QA":
                $this->ambientePruebas();
                break;
            case "PRD":
                $this->ambienteProduccion();
                break;
        }
    }

    /**
     * ambienteDesarrollo
     *
     * @return void
     */
    public function ambienteDesarrollo ()
    {
        // Mediador
        $this->dbServer = "localhost";
        $this->dbName = "mediadortv";
        $this->dbUser = "root";
        $this->dbPass = "";
        // Minerva
        $this->dbServerMinerva = "localhost";
        $this->dbNameMinerva = "portal";
        $this->dbUserMinerva = "root";
        $this->dbPassMinerva = "";
        // Cdk
        $this->serverCdk = "172.19.14.27";
        $this->usernameCdk = "mcerda";
        $this->passCdk = "makiscm";
        $this->sessionCdk = "mediadortv";
        $this->portCdk = "7780";
        // Ws Mediador
        $this->urlWsdl = "http://localhost/";
        $this->folderWsdl = "webServices/";
        $this->urlLog = "C:/var/log/mediador.log";
        $this->sshAuthenticationUser = "root";
        $this->sshAuthenticationPass = "gtdimagen";
        $this->sshHomeDirectory = "/root/";
        $this->ipDhcp = "172.28.201.4";
        $this->urlDhcp = "http://" . $this->ipDhcp . ":8081/leaseinfo?mac=";
        $this->dominioMinerva = "@tv.cl";
        // Ws Vo
        $this->urlVo = "http://172.60.100.59:7000/api";
        $this->portVo = "7000";
        $this->urlApiVo = "http://172.60.100.59:7000";
        $this->urlTokenVo = "/oauth/v2/token";
        $this->clientIdVo = "4_47xde33vdso4c8gcg84gws80ok8g0gko8oggggg0g88kokkcos";
        $this->clientSecretVo = "2uga1ntlp1q80ko880co4c80wg0oos0og8swsg88kswgwowwwg";
        $this->typeOauthVo = "client_credentials";
        $this->tenantVo = "telsur";
        $this->elementTypeVo = "STB";
        $this->technologyTvVo = "VO";
        $this->provisionVo = "1";
        $this->serviceTypeVo = "ftth";
        $this->accessTypeVo = "ftth";
        $this->typeVo = "television";
        $this->serviceTypeVoCorp = "servicios_empresa";
        // General
        $this->prefix = "GTD";
        $this->logIsFull = FALSE; // Almacena el log con detalle enviado
        $this->referer = $_SERVER['REMOTE_ADDR'];

    }

    /**
     * ambientePruebas
     *
     * @return void
     */
    public function ambientePruebas()
    {
        // Mediador
        $this->dbServer = "localhost";
        $this->dbName = "mediadortv";
        $this->dbUser = "root";
        $this->dbPass = "";
        // Minerva
        $this->dbServerMinerva = "localhost";
        $this->dbNameMinerva = "portal";
        $this->dbUserMinerva = "root";
        $this->dbPassMinerva = "";
        // Cdk
        $this->serverCdk = "172.19.14.27";
        $this->usernameCdk = "mcerda";
        $this->passCdk = "makiscm";
        $this->sessionCdk = "mediadortv";
        $this->portCdk = "7780";
        // Ws Mediador
        $this->urlWsdl = "http://localhost/";
        $this->folderWsdl = "webServices/";
        $this->urlLog = "C:/var/log/mediador.log";
        $this->sshAuthenticationUser = "root";
        $this->sshAuthenticationPass = "gtdimagen";
        $this->sshHomeDirectory = "/root/";
        $this->ipDhcp = "172.28.201.4";
        $this->urlDhcp = "http://" . $this->ipDhcp . ":8081/leaseinfo?mac=";
        $this->dominioMinerva = "@tv.cl";
        // Ws Vo
        $this->urlVo = "http://172.60.100.59:7000/api";
        $this->portVo = "7000";
        $this->urlApiVo = "http://172.60.100.59:7000";
        $this->urlTokenVo = "/oauth/v2/token";
        $this->clientIdVo = "4_47xde33vdso4c8gcg84gws80ok8g0gko8oggggg0g88kokkcos";
        $this->clientSecretVo = "2uga1ntlp1q80ko880co4c80wg0oos0og8swsg88kswgwowwwg";
        $this->typeOauthVo = "client_credentials";
        $this->tenantVo = "telsur";
        $this->elementTypeVo = "STB";
        $this->technologyTvVo = "VO";
        $this->provisionVo = "1";
        $this->serviceTypeVo = "ftth";
        $this->accessTypeVo = "ftth";
        $this->typeVo = "television";
        $this->serviceTypeVoCorp = "servicios_empresa";
        // General
        $this->prefix = "GTD";
        $this->logIsFull = FALSE; // Almacena el log con detalle enviado
        $this->referer = $_SERVER['REMOTE_ADDR'];
    }

    /**
     * ambienteProduccion
     *
     * @return void
     */
    public function ambienteProduccion()
    {
        // Mediador
        $this->dbServer = "localhost";
        $this->dbName = "mediadortv";
        $this->dbUser = "root";
        $this->dbPass = "";
        // Minerva
        $this->dbServerMinerva = "localhost";
        $this->dbNameMinerva = "portal";
        $this->dbUserMinerva = "root";
        $this->dbPassMinerva = "";
        // Cdk
        $this->serverCdk = "172.19.14.27";
        $this->usernameCdk = "mcerda";
        $this->passCdk = "makiscm";
        $this->sessionCdk = "mediadortv";
        $this->portCdk = "7780";
        // Ws Mediador
        $this->urlWsdl = "http://localhost/";
        $this->folderWsdl = "webServices/";
        $this->urlLog = "C:/var/log/mediador.log";
        $this->sshAuthenticationUser = "root";
        $this->sshAuthenticationPass = "gtdimagen";
        $this->sshHomeDirectory = "/root/";
        $this->ipDhcp = "172.28.201.4";
        $this->urlDhcp = "http://" . $this->ipDhcp . ":8081/leaseinfo?mac=";
        $this->dominioMinerva = "@tv.cl";
        // Ws Vo
        $this->urlVo = "http://172.60.100.59:7000/api";
        $this->portVo = "7000";
        $this->urlApiVo = "http://172.60.100.59:7000";
        $this->urlTokenVo = "/oauth/v2/token";
        $this->clientIdVo = "4_47xde33vdso4c8gcg84gws80ok8g0gko8oggggg0g88kokkcos";
        $this->clientSecretVo = "2uga1ntlp1q80ko880co4c80wg0oos0og8swsg88kswgwowwwg";
        $this->typeOauthVo = "client_credentials";
        $this->tenantVo = "telsur";
        $this->elementTypeVo = "STB";
        $this->technologyTvVo = "VO";
        $this->provisionVo = "1";
        $this->serviceTypeVo = "ftth";
        $this->accessTypeVo = "ftth";
        $this->typeVo = "television";
        $this->serviceTypeVoCorp = "servicios_empresa";
        // General
        $this->prefix = "GTD";
        $this->logIsFull = TRUE; // Almacena el log con detalle enviado
        $this->referer = $_SERVER['REMOTE_ADDR'];
    }

    /**
     * validateExcepcion
     *
     * @param  mixed $type
     * @return bol
     */
    public function validateExcepcion($type)
    {
        $return = FALSE;
        switch ($type) {
            case "PARAMETROS":
                $return = TRUE;
                break;
            case "DATA_TV":
                $return = TRUE;
                break;
            case "DATA_PLANES":
                $return = TRUE;
                break;
            case "DATA_STBS":
                $return = TRUE;
                break;
            case "DATA_MACS":
                $return = TRUE;
                break;
            case "DB_MEDIADOR":
                $return = TRUE;
                break;
            case "DB_MINERVA":
                $return = TRUE;
                break;
            case "PLATAFORMA":
                $return = TRUE;
                break;
        }
        return $return;
    }
}

<?php

/**
 * Class conexion con CDK Minerva
 *
 * Esta clase permite conectarse con el sistema CDK para aplicar los
 * procesos que se tienen para Minerva.
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

// Se incluye el us de los namespaces necesarios para esta clase
use Gtd\ClasesTransversal\Config;

/**
 * Minerva
 */
class Minerva
{

    public $serverCdk;
    public $usernameCdk;
    public $passCdk;
    public $sessionCdk;
    public $port;
    public $error;
    private $_config;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        // Se instancia la clase de configuración.
        $this->_config = new Config();

        $this->serverCdk        = $this->_config->serverCdk;
        $this->usernameCdk      = $this->_config->usernameCdk;
        $this->passCdk          = $this->_config->passCdk;
        $this->sessionCdk       = $this->_config->sessionCdk;
        $this->port             = $this->_config->portCdk;
    }

    /**
     * getChannelsByLineup
     *
     * @param  mixed $lineup
     * @return void
     */
    function getChannelsByLineup($lineup)
    {
        $canales = array();

        $link  = "http://" . $this->serverCdk . ":" . $this->port;
        $link .= "/dataservices/cdk?SP=md_liv.get_lineup_dictionary('";
        $link .= $this->sessionCdk . "','";
        $link .= $lineup . "',?,?,?, ?int status )";
        $salida = @simplexml_load_file($link);
        if ($salida !== FALSE) {
            foreach ($salida->{'md_liv.get_lineup_dictionary'}->RS1->row as $row) {
                $canales[] = array("channel_id" => (int) $row['CHANNEL_ID'],
                                "channel_number" => (int) $row["CHANNEL_NUMBER"]);
            }
            return $canales;
        } else {
            return FALSE;
        }
    }

    /**
     * getChannelsByPackage
     *
     * @param  mixed $package
     * @return void
     */
    function getChannelsByPackage($package)
    {
        $canales = array();

        $link  = "http://" . $this->serverCdk . ":" . $this->port;
        $link .= "/dataservices/cdk?SP=md_srv.get_channel_package_info('";
        $link .= $this->sessionCdk . "','";
        $link .= $package . "',?,?,?, ?int status )";
        $salida = @simplexml_load_file($link);
        if ($salida !== FALSE) {
            foreach ($salida->{'md_srv.get_channel_package_info'}->RS2->row as $row) {
                $canales[] = (int) $row['CHANNEL_ID'];
            }
            return $canales;
        } else {
            return FALSE;
        }
    }

    /**
     * getChannelPackages
     *
     * @param  mixed $customerId
     * @return void
     */
    function getChannelPackages($customerId)
    {
        $planes = array();
        $link  = "http://" . $this->serverCdk . ":" . $this->port;
        $link .= "/dataservices/cdk?SP=md_cst.get_customer_services ('";
        $link .= $this->sessionCdk . "','";
        $link .= $customerId . "',?, ?int status )";
        $salida = @simplexml_load_file($link);
        if ($salida !== FALSE) {
            foreach ($salida->{'md_cst.get_customer_services'}->RS1->row as $row) {
                if ($row['SERVICE_CODE'] == 'CHPKG') {
                    $planes[] = $row['SERVICE_ID'];
                }
            }
            return $planes;
        } else {
            return FALSE;
        }
    }

    function getCustomerInfo($customerID)
    {
        $link  = "http://" . $this->serverCdk . ":" . $this->port;
        $link .= "/dataservices/cdk?SP=md_cst.get_customer_account('";
        $link .= $this->sessionCdk . "','";
        $link .= $customerID . "',?,?,?,?int status)";
        $salida = @simplexml_load_file($link);
        if ($salida !== FALSE) {
            foreach ($salida->{'md_cst.get_customer_account'}->RS2->row as $row) {
                $region = $row['REGION_ID'];
            }
            return $region;
        } else {
            return FALSE;
        }
    }

    /**
     * getDeviceInfo
     *
     * @param  mixed $mac
     * @return void
     */
    function getDeviceInfo($mac)
    {
        //obtiene deviceid
        $link  = "http://" . $this->serverCdk . ":" . $this->port;
        $link .= "/dataservices/cdk?SP=md_dev.get_device_list('";
        $link .= $this->sessionCdk . "','5','" . $mac;
        $link .= "','N','1','200',?,?,?int status)";
        $response = @simplexml_load_file($link);
        if($response !== FALSE){
            $deviceList_response = $response->{'md_dev.get_device_list'};
            $status = $deviceList_response["status"];
            if ($status != 0) {
                return FALSE;
            }
            $deviceList = array();
            $count = count($deviceList_response->RS1->row);
            for ($i = 0; $i < $count ; $i++) {
                $deviceList[$i] = array();
                foreach ($deviceList_response->RS1->row[$i]->attributes() as $key => $value) {
                    $deviceList[$i][$key] = (string) $value;
                }
            }
            $device_id = $deviceList[0]['DEVICE_ID'];
            if ($device_id == ""){
                return FALSE;
            }
            return $device_id;
        }else{
            return FALSE;
        }
    }

    /**
     * getLineupByRegion
     *
     * @param  mixed $region
     * @return void
     */
    function getLineupByRegion($region)
    {
        $link  = "http://" . $this->serverCdk . ":" . $this->port;
        $link .= "/dataservices/cdk?SP=md_sys.get_ISP_dictionary('";
        $link .= $this->sessionCdk;
        $link .= "', ?, ?, ?, ?, ?,?, ?, ?, ?, ?, ?int status)";
        $salida = @simplexml_load_file($link);
        if($salida !== FALSE){
            foreach ($salida->{'md_sys.get_ISP_dictionary'}->RS3->row as $row) {

                $regions = $row['Region_ID'];
                // es la fila correcta, obtengo el lineup
                if ((int) $regions == (int) $region) {
                    $lineupID = (int) $row['LineUp_ID'];
                    break;
                }
            }
            return $lineupID;
        }else{
            return FALSE;
        }
    }

    /**
     * getServicesInfo
     *
     * @param  mixed $services_menuid
     * @param  mixed $lineUpId
     * @return void
     */
    function getServicesInfo($services_menuid, $lineUpId)
    {
        $services = array();
        $link  = "http://" . $this->serverCdk . ":" . $this->port;
        $link .= "/dataservices/cdk?SP=md_srv.get_services ('";
        $link .= $this->sessionCdk . "',";
        $link .=$lineUpId . ",?,?,?,?,?,?,?,?int status)";
        $salida = @simplexml_load_file($link);
        if ($salida !== FALSE) {
            foreach ($salida->{'md_srv.get_services'}->RS2->row as $row) {

                $plan = $row['SERVICE_MENU_ID'];
                if (in_array($plan, $services_menuid)) {
                    $channel_pkg = $row['CHANNEL_PKG_ID'];
                    $services[] = $channel_pkg;
                }
            }
            return $services;
        } else {
            return FALSE;
        }
    }

    /**
     * setSkinforDevice
     *
     * @param  mixed $mac
     * @return void
     */
    function setSkinforDevice($mac)
    {
        //asigna skin al deco
        //amino 883 ,aminoStgo5.7_skin,DE
        //xavi 783 ,Skin_GTD5.7,DE
        $language = "DE";
        $skinid = 0;
        $theme = "";
        if (substr($mac, 0, 6) == "E09153") {
            $skinid = 783;
            $theme = "Skin_GTD5.7";
        } elseif (substr($mac, 0, 6) == "000202") {
            $skinid = 883;
            $theme = "aminoStgo5.7_skin";
        }
        $idDevice = $this->getDeviceInfo($mac);
        if (!$idDevice) {
            return FALSE;
        }
        $link  = "http://" . $this->serverCdk . ":" . $this->port;
        $link .= "/dataservices/cdk?SP=md_dev.set_skin_for_device('";
        $link .= $this->sessionCdk . "','" . $idDevice . "','" . $skinid;
        $link .= "','" . $theme . "','" . $language . "',?int status)";
        $response = @simplexml_load_file($link);
        if ($response !== FALSE) {
            $stat_response = $response->{'md_dev.set_skin_for_device'};
            $status = $stat_response["status"];
            if ($status != 0){
                return FALSE;
            }else{
                return TRUE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * logIn
     *
     * @return void
     */
    function logIn()
    {
        $in  ="http://" . $this->serverCdk . ":" . $this->port ;
        $in .="/dataservices/cdk?SP=md_adm.login_with_session('";
        $in .= $this->usernameCdk . "','" . $this->passCdk . "','";
        $in .= $this->sessionCdk . "',?int status)";
        $entrada = @simplexml_load_file($in);
        if ($entrada !== FALSE) {
            if ($entrada->children()->{'md_adm.login_with_session'}->attributes()->status != 0) {
                $this->error  = "ERROR CON LA CONEXION A MINERVA: ";
                $this->error .= $entrada->children()->{'md_adm.login_with_session'}->attributes()->status;
                $this->logOut();
                return FALSE;
            } else {
                return TRUE;
            }
        }else{
            return FALSE;
        }
    }

    /**
     * logOut
     *
     * @return void
     */
    function logOut()
    {

        $out  = "http://" . $this->serverCdk . ":";
        $out .= $this->port;
        $out .= "/dataservices/cdk?SP=md_adm.log_off('";
        $out .= $this->sessionCdk . "',?,?int status)";
        $salida = @simplexml_load_file($out);
        if ($salida !== FALSE) {
            if ($salida->children()->{'md_adm.log_off'}->attributes()->status != 0) {
                $this->error  = "ERROR EN LOGOUT A MINERVA: ";
            }
            return TRUE;
        } else {
            return FALSE;
        }
    }
}

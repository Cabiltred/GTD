<?php

/**
 * Class TvModificaServicio  modificacion de Servicio de Televisión
 *
 * Esta clase permite realizar el modificacion de Servicio para Minerva y VO
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
 * @package ClasesWs
 * @author Macarena Cerda Mora      <mcerda@grupogtd.com>
 * @author Franciso Sandoval Iturra <Francisco.Sandoval@grupogtd.com>
 * @copyright 2020 GTD
 * @license https://opensource.org/licenses/MIT MIT License
 * @link http://localhost/
 *
 */

namespace Gtd\ClasesWs;

// Se incluye el us de los namespaces necesarios para esta clase
use Gtd\ClasesTransversal\Config;
use Gtd\ClasesTransversal\Util;
use Gtd\ClasesTransversal\Db;
use Gtd\ClasesTransversal\Sql;
use Gtd\ClasesTransversal\Ws;
use Gtd\ClasesTransversal\Vo;

/**
 *
 * TvModificaServicio
 */
class TvModificaServicio
{
    private $_instanciaSubCuenta;
    private $_macs;
    private $_series;
    private $_planes;
    private $_nombre;
    private $_apellido;
    private $_direccion;
    private $_ciudad;
    private $_tipoServicio;
    private $_region;
    private $_login;
    private $_accion;
    private $_totalDecosCliente;
    private $_info;         // Datos originales
    private $_aux;          // Datos procesados
    private $_idConsulta;   // Almacena el registro de la consulta de a Db
    private $_return;       // Estructura de retorno
    private $_config;       // Instancia variables de configuracion
    private $_util;         // Instancia de funciones utiles
    private $_dbMediador;   // Instancia para base de datos Mediador
    private $_dbMinerva;    // Instancia para base de datos Minerva
    private $_sqlMediador;  // Instancia clase procesos Sql Mediador
    private $_sqlMinerva;   // Instancia clase procesos Sql Minerva

    /**
     * __construct
     *
     * @param  mixed $info
     * @return void
     */
    public function __construct($info)
    {
        $this->_instanciaSubCuenta  = $info["instanciaSubCuenta"];
        $this->_macs                = $info["macs"];
        $this->_series              = $info["series"];
        $this->_planes              = $info["planes"];
        $this->_nombre              = $info["nombre"];
        $this->_apellido            = $info["apellido"];
        $this->_direccion           = $info["direccion"];
        $this->_ciudad              = $info["ciudad"];
        $this->_tipoServicio        = $info["tipoServicio"];
        $this->_region              = $info["region"];
        $this->_login               = $info["login"];
        $this->_accion              = $info["accion"];
        $this->_totalDecosCliente   = $info["totalDecosCliente"];
        $this->_info                = $info;
        $this->_aux                 = [];
        $this->_dbMediador          = FALSE;
        $this->_dbMinerva           = FALSE;

        // Se instancia la clase de configuración.
        $this->_config = new Config();
        // Se instancia la clase de funciones utiles.
        $this->_util = new Util();

        // Se construye la estructura de retorno a utlizar, inicia vacia
        $this->_return = array (
                        "default" => array(
                                    "status"  => "",
                                    "data"    => "",
                                    "message" => "",
                                    "error"   => array("faultCode"   => "",
                                                    "faultActor"  => "",
                                                    "faultString" => "",
                                                    "faultDetail" => "",
                                                    "Default"     => ""),
                                    )
                            );

    }

    /**
     * altaServicio
     *
     * Esta función genera la modificación de servicio para
     * las plataformas definidas
     *
     * @return struct
     */
    public function modificaServicio()
    {
        $return = [];
        // Almacena Log de seguimiento
        $this->_util->setLog(
            $this->_config->urlLog,
            "M O D  I F I C A C I O N   D E   S E R V I C I O",
            "MEDIADOR",
            "Inicia modificacion de servicio en Minerva y Vo",
            "INICIO",
        );
        // Se obtiene la conexion con la base de datos Mediador
        $validaDb        = $this->_validaConexionDb();
        // Se obtiene la conexion con la base de datos Minerva
        $validaDbMinerva = $this->_validaConexionDbMinerva();
        // Se evalua que las dos conexiones a la base de datos sea correcta
        if ($validaDb && $validaDbMinerva) {
            // Se registra transaccion al iniciar proceso
            $this->_idConsulta = $this->_util->startTransaction(
                "modifica_servicio",
                $this->_sqlMediador,
                $this->_info,
            );
            // Se validan los parametros de entrada
            $validaParametros = $this->_validaParametros();
            // Se valida si los parametros traen la información correcta
            if ($validaParametros === TRUE) {
                // Se invoca el proceso que modifica el servicio en Minerva
                $return = $this->_modificaServicioMinerva();
                $this->_return["default"] = $return["default"];
                $this->_return["minerva"] = $return["minerva"];
                if ($this->_return["default"]["status"] === FALSE) {
                    return $this->_return;
                }
                // Se invoca el proceso que modifica el servicio en VO
                $return = $this->_modificaServicioVO();
                $this->_return["default"] = $return["default"];
                $this->_return["vo"]      = $return["vo"];
                if ($this->_return["default"]["status"] === FALSE) {
                    return $this->_return;
                }
                // Se evalua si ocurrio alguna excepcion
                if ($this->_return["default"]["status"] !== FALSE) {
                    // Se registra transaccion al finalizar proceso
                    $this->_idConsulta = $this->_util->endTransaction(
                        "respuesta_modifica_servicio",
                        $this->_sqlMediador,
                        $this->_return,
                        $this->_idConsulta
                    );
                    // Obtiene la respuesta definitiva a retornar en el WS
                    $respuesta = $this->_util->getResponseWs(
                        $this->_return
                    );
                    $this->_return["default"]["respuesta"] = $respuesta;
                    // Almacena Log de seguimiento
                    $this->_util->setLog(
                        $this->_config->urlLog,
                        "M O D I F I C A C I O N   D E   S E R V I C I O",
                        "MEDIADOR",
                        "Finaliza modificacion de servicio en Minerva y Vo",
                        "FIN",
                    );
                }
            }
        }
        return $this->_return;
    }

    /**
     * _modificaServicioMinerva
     *
     * Esta función genera la modificación  de servicio en la plataforma Minerva
     *
     * @return void
     */
    private function _modificaServicioMinerva()
    {
        $return = [];
        $ws = new Ws();
        // Se envia la instancia de la base de datos del mediador a Minerva
        $ws->setInstanceDb($this->_dbMediador);
        // Se envia la instancia de la base de datos de minerva a Minerva
        $ws->setInstanceDbMinerva($this->_dbMinerva);
        // Verifica que la plataforma Minerva este activa para aprovisionamiento
        $rsPlataforma = $this->_sqlMediador->getPlatform("Minerva");
        // Verifica que la plataforma Vo este activa para aprovisionamiento
        if ($rsPlataforma === TRUE) {
            // Obtiene la Url del Ws a ser invocado
            $urlWs =  $this->_sqlMediador->getUrlWs("MODIFICA_SERVICIO_V2");
            $rsVAlidaUrl = $this->_util->validateUrl($urlWs);
            // Valida si la URL del WS de Minerva esta respondiendo
            if ($rsVAlidaUrl === TRUE) {
                // Prepara los datos para Aprovisionamiento de Minerva
                $validaPrepara = $this->_prepararDatosMinerva();
                if ($validaPrepara) {
                    // Procesa Modificación de servicio  Minerva
                    // La respuesta del proceso de modificacion es almacenada en
                    // $this->_return tanto la respuesta del proceso como la
                    // respuesta del error, en caso de existir
                    $return = $this->setModificaServicioMinerva($ws, $this->_aux);
                }
            } else {
                // informacion para construir log de respuesta y error
                $data = [
                    "messageLog"    => "URL WS MINERVA NO RESPONDE",
                    "type"          => "URL",
                    "platform"      => "MINERVA",
                    "state"         => FALSE,
                    "detail"        => "",
                    "result"        => [
                        "estado" => "URL WS MINERVA NO RESPONDE",
                        "estado_desc" => "NOOK"
                    ]
                ];
                $return = $this->_util->setResponse($ws, $data);
                // Almacena Log de seguimiento
                $this->_util->setLog(
                    $this->_config->urlLog,
                    "WS Minerva:ERROR",
                    "MEDIADOR",
                    $data
                );
            }
        }else{
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "NO ACTIVO MINERVA",
                "type"          => "PLATAFORMA",
                "platform"      => "MINERVA",
                "state"         => FALSE,
                "detail"        => "",
                "result"        => [
                    "estado"=>"NO ACTIVO MINERVA",
                    "estado_desc" => "NOOK"
                ]
            ];
            $return = $this->_util->setResponse($ws, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Plataforma Minerva:INACTIVA",
                "MEDIADOR"
            );
        }
        return $return;
    }

    /**
     * _modificaServicioVo
     *
     * Esta función genera la modificación de servicio en la plataforma VO
     *
     * @return strunct
     */
    private function _modificaServicioVo()
    {
        $return = [];
        $vo = new Vo();
        // Se envia la instancia de la base de datos del mediador a VO
        $vo->setInstanceDb($this->_dbMediador);
        $rsPlataforma = $this->_sqlMediador->getPlatform("Vo");
        // Verifica que la plataforma Vo este activa para modificación
        if ($rsPlataforma === TRUE) {
            $rsVAlidaUrl = $this->_util->validateUrl($this->_config->urlVo);
            // Valida si la URL del WS de VO esta respondiendo
            if ($rsVAlidaUrl === TRUE) {
                // Obtiene el token para conexxion con el WS
                $rsToken = $vo->setToken();
                // Valida que el token se haya generado
                if($rsToken === TRUE) {
                    // Prepara los datos para Modificación de servicio de VO
                    $validaPrepara = $this->_prepararDatosVo();
                    if ($validaPrepara) {
                        // Procesa Modificacion de servicio VO
                        $return = $this->_setModificaServicioVo(
                            $vo,
                            $this->_aux
                        );
                    }
                } else {
                    // informacion para construir log de respuesta y error
                    $data = [
                        "messageLog"    => "TOKEN VO NO SE PUDO GENERAR",
                        "type"          => "TOKEN",
                        "platform"      => "VO",
                        "state"         => FALSE,
                        "detail"        => "",
                        "result"        => [
                            "estado" => "TOKEN VO NO SE PUDO GENERAR",
                            "estado_desc" => "NOOK"
                        ]
                    ];
                    $return = $this->_util->setResponse($vo, $data);
                    // Almacena Log de seguimiento
                    $this->_util->setLog(
                        $this->_config->urlLog,
                        "API TOKEN VO:ERROR",
                        "MEDIADOR",
                        $data
                    );
                }
            }else{
                // informacion para construir log de respuesta y error
                $data = [
                    "messageLog"    => "URL WS VO NO RESPONDE",
                    "type"          => "URL",
                    "platform"      => "VO",
                    "state"         => FALSE,
                    "detail"        => "",
                    "result"        => [
                        "estado" => "URL WS VO NO RESPONDE",
                        "estado_desc" => "NOOK"
                    ]
                ];
                $return = $this->_util->setResponse($vo, $data);
                // Almacena Log de seguimiento
                $this->_util->setLog(
                    $this->_config->urlLog,
                    "API VO:ERROR",
                    "MEDIADOR",
                    $data
                );
            }
        }else {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "NO ACTIVO VO",
                "type"          => "PLATAFORMA",
                "platform"      => "VO",
                "state"         => FALSE,
                "detail"        => "",
                "result"        => [
                    "estado" => "NO ACTIVO VO",
                    "estado_desc" => "NOOK"
                ]
            ];
            $return = $this->_util->setResponse($vo, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Plataforma VO:INACTIVA",
                "MEDIADOR"
            );
        }
        return  $return;
    }

    /**
     * getDescripcionEstado
     *
     * @param  mixed $codigo
     * @return void
     */
    public function getDescripcionEstado($codigo)
    {
        $str = "";
        switch ($codigo) {
            case "-2":
                // Genera mensaje de retorno de error
                $str = $codigo . " - ERROR GENERAL";
                break;
            case "-3":
                // Genera mensaje de retorno de error
                $str = $codigo . " - DATA REQUERIDA NO FUE ENCONTRADA";
                break;
            case "-4":
                // Genera mensaje de retorno de error
                $str = $codigo . " - FORMATO FECHA NO VALIDO";
                break;
            case "-6":
                // Genera mensaje de retorno de error
                $str = $codigo . " - INFORMACION DE CLIENTE NO FUE ADICIONADA";
                break;
            case "-11":
                // Genera mensaje de retorno de error
                $str = $codigo . " - CLIENTE YA EXISTE";
                break;
            case "-12":
                // Genera mensaje de retorno de error
                $str = $codigo . " - INFORMACION DE CLIENTE NO FUE ADICIONADA";
                break;
            case "-14":
                // Genera mensaje de retorno de error
                $str = $codigo . " - ID DE SERVICIO NO VALIDO";
                break;
            case "-15":
                // Genera mensaje de retorno de error
                $str = $codigo . " - REGION NO VALIDA";
                break;
            case "-27":
                // Genera mensaje de retorno de error
                $str = $codigo . " - CLIENTE POSEE MAS DE 64 PLANES";
                break;
            case "-95":
                // Genera mensaje de retorno de error
                $str = $codigo . " - INSTANCIA POSEE MAS DE 20 CARACTERES";
                break;
            case "-100":
                // Genera mensaje de retorno de error
                $str = $codigo . " - EXCEPCION DE SERVICIO";
                break;
            default:
                $str = $codigo . " - ERROR NO CODIFICADO";
        }
        return $str;
    }

    /**
     * _prepararDatosMinerva
     *
     * Esta función prepara los datos previos al consumo del WS de la
     * la plataforma Minerva
     *
     * @return struct
     */
    private function _prepararDatosMinerva()
    {
        $retorno = FALSE;

        // Fecha hoy
        $dateToday = date("d/m/Y");

        // Prepara el login
        $login = substr($this->_login, 0, 10);
        $this->_aux["minerva"]["login"] = $login;

        // Prepara Instancia, Rut y Subcuentas
        $resultado  = $this->_util->splitAccount($this->_instanciaSubCuenta);
        $instancia  = $resultado["instancia"];
        $subcuentas = $resultado["subcuentas"];
        $rut = $resultado["rut"];
        $this->_aux["minerva"]["instanciaRutSubCuenta"] = $this->_instanciaSubCuenta;
        $this->_aux["minerva"]["instancia"]             = $instancia;
        $this->_aux["minerva"]["rut"]                   = $rut;
        $this->_aux["minerva"]["subcuentas"]            = $subcuentas;

        // Prepara tipoServicio
        $tipoServicio = $this->_tipoServicio;
        $this->_aux["minerva"]["tipoServicio"] = $tipoServicio;

        // Prepara macs
        $macs = $this->_util->parseData($this->_macs);
        $this->_aux["minerva"]["macs"] = $macs;

        // Prepara stbs
        $stbs= count($macs);
        $this->_aux["minerva"]["stbs"] = $stbs;

        // Prepara series
        $series = $this->_util->parseData($this->_series);
        $this->_aux["minerva"]["series"] = $series;


        // Prepara planes
        $this->_aux["minerva"]["planesOriginal"] = $this->_planes;
        $planes = $this->_util->parseData($this->_planes,",");
        $ff = 1; // pos Fecha_fin inicial
        $numeroPlanes = "";
        foreach ($planes as $key => $value) {
            $pM = "";
            if (strstr($value, "|")
            || (strlen($value) <= 4 && !empty($value))) {
                $planesVector = $this->_util->parseData($value);
                foreach ($planesVector as $plan) {
                    if ($plan != "''") {
                        $codPlan = $this->_sqlMediador->getCodeMinerva($plan);
                        $pM .= $codPlan . "|";
                        $numeroPlanes .= $codPlan . "|";
                    }
                }
                $ff += 3;
                $planes[$key] = trim($pM, "|");
            }
        }
        $planes = implode(",", $planes);
        $this->_aux["minerva"]["planes"] = $planes;
        $this->_aux["minerva"]["numeroPlanes"] = $numeroPlanes;

        // Prepara email
        /*$dominio = $this->_config->dominioMinerva;
        $email = $this->_aux["minerva"]["instancia"] . $dominio;
        $this->_aux["minerva"]["email"] = $email;*/

        // Prepara DVR
        $dvr = FALSE;
        // Se recorren las series para verificar si el decodificador
        // permite grabacion de información
        foreach ($series as $key => $value) {
            if (substr($value, 0, 6) == "XV1512"
                || substr($value, 0, 2) == "G7") {
                $dvr = TRUE;
                break;
            }
        }
        $this->_aux["minerva"]["dvr"] = $dvr;

        // Prepara servicios
        $servicios  = "";
        $servicios  = "'" . $dateToday . "','',";
        $servicios .= $this->_sqlMediador->getServiceByName("INTERNET") . "|";
        $servicios .= $this->_sqlMediador->getServiceByName("HD") . "|";
        $servicios .= $this->_sqlMediador->getServiceByName("WIDGETS") . "|";
        $servicios .= $this->_sqlMediador->getServiceByName("CALLERID") . "|";
        $servicios .= $this->_sqlMediador->getServiceByName("Remote Scheduling");
        // si la seria indica que es un DVR se adiciona el servicio
        if ($dvr===TRUE) {
            $servicios .= "|" . $this->_sqlMediador->getServiceByName("DVR");
        }
        $this->_aux["minerva"]["servicios"] = $servicios;

        //Prepara region
        //para los casos de otras regiones zonales(Viña,rancagua,china)
        $region = $this->_region;
        if ($region == "RM") {
            if (strstr($this->_tipoServicio, "IPTV")) {
                //region unica para IPTV (853)
                $region = "IPTV_RESIDENCIAL_STGO";
            }
        }
        // Se obtiene el codigo de la región
        $codigoRegion = $this->_sqlMediador->getRegion($region);
        $this->_aux["minerva"]["region"] = $region;
        $this->_aux["minerva"]["codigoRegion"] = $codigoRegion;

        // Prepara estructura de consumo de WS para aprovisionamiento
        // del servicio de alta en Minerva
        $prefijo = $this->_config->prefix;
        $parametros = array("cFirstName" => $this->_nombre,
                            "cLastName" => $this->_apellido,
                            "cStreet" => $this->_direccion,
                            "cCity" => $this->_ciudad,
                            "cStbCount" => $stbs,
                            "cLogin" => $login,
                            "cPassword" => "",
                            "cPin" => "",
                            "cCustomerId" => $prefijo . $instancia,
                            "cEnableStreamManagment" => "N",
                            "cTotlaAttainableBandwidth" => "100000",
                            "cRegId" => $codigoRegion,
                            );
        $this->_aux["minerva"]["parametros"] = $parametros;
        $retorno = TRUE;

        // Almacena Log de seguimiento
        $this->_util->setLog(
            $this->_config->urlLog,
            "Preparacion de datos Minerva:OK",
            "DATOS",
            $this->_aux["minerva"]
        );
        return $retorno;
    }

    /**
     * _prepararDatosVo
     *
     * Esta función prepara los datos previos al consumo del WS de la
     * plataforma VO
     *
     * @return struct
     */
    private function _prepararDatosVo()
    {
        $retorno = FALSE;
        $resultado  = $this->_util->splitAccount($this->_instanciaSubCuenta);

        // Prepara rut
        $rut = $resultado["rut"];
        $this->_aux["vo"]["rut"]     = $rut;

        // Prepara instancia
        $instancia  = $resultado["instancia"];
        $prefijo = $this->_config->prefix;
        $this->_aux["vo"]["serviceCode"] = $prefijo . $instancia;

        // Prepara name
        $name = $this->_nombre . " " . $this->_apellido;
        $this->_aux["vo"]["name"]     = $name;

        // Prepara address
        $address = $this->_direccion;
        $this->_aux["vo"]["address"]     = $address;

        // Prepara address
        $phone = "";
        $this->_aux["vo"]["phone"]     = $phone;

        // Prepara comment
        $comment = "Creado desde Mediador";
        $this->_aux["vo"]["comment"]     = $comment;

        // Prepara macs
        $macs = $this->_util->parseData($this->_macs);
        $this->_aux["vo"]["macs"] = $macs;

        // Prepara stbs
        $stbs = $this->_totalDecosCliente;
        $this->_aux["vo"]["stbs"] = $stbs;

        // Prepara series
        $series = $this->_util->parseData($this->_series);
        $this->_aux["vo"]["series"] = $series;

        $prefijoSeries = $this->_util->splitSerie($series);
        $this->_aux["vo"]["prefijoSeries"] = $prefijoSeries;

        // Prepara planes
        $planes = $this->_util->parseData($this->_planes, ",");
        // Se carga la posicion 2, la cual es la que trae los planes
        $strPlanes = $planes[2];
        $vecPlanes = $this->_util->parseData($strPlanes, "|");
        $planesVo = [];
        foreach ($vecPlanes as $key => $value) {
            $planesVo[] = $value;
        }
        $this->_aux["vo"]["planes"]     = $planesVo;

        //Prepara region
        //para los casos de otras regiones zonales(Viña,rancagua,china)
        $region = $this->_region;
        if ($region == "RM") {
            if (strstr($this->_tipoServicio, "IPTV")) {
                //region unica para IPTV (853)
                $region = "IPTV_RESIDENCIAL_STGO";
            }
        }
        // Se obtiene el codigo de la región
        $codigoRegion = $this->_sqlMediador->getRegion($region);
        $this->_aux["vo"]["region"] = $region;
        $this->_aux["vo"]["codigoRegion"] = $codigoRegion;

        $retorno = TRUE;
        // Almacena Log de seguimiento
        $this->_util->setLog(
            $this->_config->urlLog,
            "Preparacion de datos VO:OK",
            "DATOS",
            $this->_aux["vo"]
        );
        return $retorno;
    }

    /**
     * setModificaServicioMinerva
     *
     * @param  mixed $ws
     * @param  mixed $info
     * @return void
     */
    public function setModificaServicioMinerva($ws, $info)
    {
        $return         = [];
        $objSql         = $this->_sqlMediador;
        $parametros     = $info["minerva"]["parametros"];
        $instancia      = $info["minerva"]["instancia"];
        // Consumo del servicio de Aprovisionamiento de Minerva
        $result = $ws->consumeWs(
            "MODIFICA_SERVICIO_V2",
            "TraeRegistros",
            $parametros
        );
        //Simular respuesta

        // INICIO DE SIMULACION
        //$result = "respuesta";    // Valida respuesta pero no valida
        //$result[0]["estado"] = 5; // Valida la respuesta pero no el estado
        //$result[0]["estado"] = 0;   // Se simula que la respuesta es valida
        //$exitosM = 2;               // Se simula que se adicionaron 2 decos
        // FIN DE SIMULACION

        // Valida el consumo del WS
        if ($result) {
            error_log("provisionando en Minerva");
            if (is_array($result)) {
                error_log("conexion alta_servicio");
                if ($result[0]["estado"] == 0 || $result[0]["estado"] == 1002) {
                    // Se obtienen las Macs Actuales
                    $currentMacs = array();
                    $currentMacs = $ws->getStbs($instancia);
                    $info["minerva"]["currentMacs"] = $currentMacs;
                    // Aprovisionamiento de decos en Minerva
                    $ws->setStbMinerva($info, FALSE);
                    // Elimina Decos que no son del cliente
                    $resultRefresh = $ws->setStbMinervaAdicional($info);
                    if($resultRefresh["refresca_cas"] === TRUE) {
                        // Se define respuesta
                        $estado = "MODIFICADO CORRECTAMENTE";
                        // informacion para construir log de respuesta y error
                        $data = [
                            "messageLog"    => "OK:PROCESO CORRECTO",
                            "type"          => "OK",
                            "platform"      => "MINERVA",
                            "state"         => TRUE,
                            "detail"        => "",
                            "input"         => implode("_", $parametros),
                            "output"        => $estado,
                            "ws"            => "MODIFICA_SERVICIO_V2",
                            "result"        => [
                                "estado" => $estado,
                                "estado_desc" => "OK"
                            ]
                        ];
                        $return = $this->_util->setResponse(
                            $ws,
                            $data,$objSql
                        );
                        // Almacena Log de seguimiento
                        $this->_util->setLog(
                            $this->_config->urlLog,
                            "Modifica Servicio Minerva:OK",
                            "MEDIADOR"
                        );
                    } else {
                        $estado = "WS REFRESCAR CAS NO SE PURO EJECUTAR";
                        // informacion para construir log de respuesta y error
                        $data = [
                            "messageLog"    => "ERROR: WS REFRESCA_CAS NO SE EJECUTO",
                            "type"          => "REFRESCAR",
                            "platform"      => "MEDIADOR",
                            "state"         => FALSE,
                            "detail"        => "",
                            "input"         => implode("_", $parametros),
                            "output"        => $estado,
                            "ws"            => "REFRESCAR_CAS",
                            "result"        => [
                                "estado" => $estado,
                                "estado_desc" => "NOOK"
                            ]
                        ];
                        $return = $this->_util->setResponse(
                            $ws,
                            $data,
                            $objSql
                        );
                        // Almacena Log de seguimiento
                        $this->_util->setLog(
                            $this->_config->urlLog,
                            "WS Mediador Refrescar Cas:ERROR",
                            "MEDIADOR",
                            $data
                        );
                    }

                } else {
                    $detail = $this->getDescripcionEstado($result[0]["estado"]);
                    $estado = "WS ALTA EL ESTADO DE RESPUESTA NO ES CORRECTO: ";
                    $estado .= $detail;
                    // informacion para construir log de respuesta y error
                    $data = [
                        "messageLog"    => "ERROR: WS ALTA ESTADO NO CORRECTO",
                        "type"          => "ESTADO",
                        "platform"      => "MINERVA",
                        "state"         => FALSE,
                        "detail"        => $detail,
                        "input"         => implode("_", $parametros),
                        "output"        => $estado,
                        "ws"            => "ALTA_SERVICIO",
                        "result"        => [
                            "estado" => $estado,
                            "estado_desc" => "NOOK"
                        ]
                    ];
                    $return = $this->_util->setResponse($ws, $data, $objSql);
                    // Almacena Log de seguimiento
                    $this->_util->setLog(
                        $this->_config->urlLog,
                        "WS Minerva Estado:ERROR",
                        "MEDIADOR",
                        $data
                    );
                }
                error_log("Resultado de alta_servicio" . $estado);
            } else {
                $estado = "RESPUESTA DE WS ALTA NO ES CORRECTA";
                // informacion para construir log de respuesta y error
                $data = [
                    "messageLog"    => "ERROR: WS ALTA RESPONDE INCORRECTO",
                    "type"          => "RESPUESTA",
                    "platform"      => "MINERVA",
                    "state"         => FALSE,
                    "detail"        => "",
                    "input"         => implode("_", $parametros),
                    "output"        => $estado,
                    "ws"            => "ALTA_SERVICIO",
                    "result"        => [
                        "estado" => $estado,
                        "estado_desc" => "NOOK"
                    ]
                ];
                $return = $this->_util->setResponse($ws, $data, $objSql);
                // Almacena Log de seguimiento
                $this->_util->setLog(
                    $this->_config->urlLog,
                    "WS Minerva Respuesta:ERROR",
                    "MEDIADOR",
                    $data
                );
            }
            error_Log("estadoMinerva" . $estado);
        } else {
            $estado = "SIN CONEXION";
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: FALLO CONSUMO WS ALTA DE SERVICIO",
                "type"          => "CONEXION",
                "platform"      => "MINERVA",
                "state"         => FALSE,
                "detail"        => "",
                "input"         => implode("_", $parametros),
                "output"        => $estado,
                "ws"            => "ALTA_SERVICIO",
                "result"        => [
                    "estado" => $estado,
                    "estado_desc" => "NOOK"
                ]
            ];
            $return = $this->_util->setResponse($ws, $data, $objSql);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "WS Minerva:ERROR",
                "MEDIADOR",
                $data
            );
        }
        return $return;
    }

    /**
     * _setModificaServicioVo
     *
     * @param  mixed $info
     * @param  mixed $info
     * @return void
     */
    private function _setModificaServicioVo($vo, $info)
    {
        $return   = [];
        $objSql = $this->_sqlMediador;

        // Se valida que los planes que se enviaron esten activos y disponibles
        // todos los planes deben validarse como TRUE, si al menos uno de los
        // planes enviados no esta bien, el proceso se desecha
        $rsPlan = $vo->validatePlanes($info);
        if ($rsPlan === TRUE) {
            // Se verifica que el cliente exista, en caso que no exista se
            // envia a crear, solo si el proceso retorna TRUE se continua
            $rsCliente = $vo->setCliente($info);
            if ($rsCliente === TRUE) {
                // Se verifica que el elemento exista, en caso de no existir se
                // Crea, y si se encuentra ya creado se actualiza
                $rsElemento =  $vo->setElementos($info);
                if ($rsElemento === TRUE) {
                    // Se verifica que el servicio exista, en caso que no exista
                    // se crea, y si existe se actualiza
                    $rsServicio = $vo->setServicio($info);
                    if ($rsServicio === TRUE) {
                        //Respuesta OK
                        $estado = "MODIFICADO CORRECTAMENTE";
                        // informacion para construir log de respuesta y error
                        $data = [
                            "messageLog" => "OK: PROCESO CORRECTO",
                            "type"       => "OK",
                            "platform"   => "VO",
                            "state"      => TRUE,
                            "detail"     => "",
                            "input"      => $info["vo"]["serviceCode"],
                            "output"     => $estado,
                            "ws"         => "api/service/",
                            "result"     => [
                                "estado" => $estado,
                                "estado_desc" => "OK"
                            ]
                        ];
                        $return = $this->_util->setResponse(
                            $vo,
                            $data,
                            $objSql
                        );
                        // Almacena Log de seguimiento
                        $this->_util->setLog(
                            $this->_config->urlLog,
                            "Modificacion Servicio VO:OK",
                            "MEDIADOR"
                        );
                    } else {
                        // Se obtiene la respuesta de la causa del fallo en el WS
                        $responseApi = $vo->getResponseApi();
                        $estado = "NO SE PUDO MODIFICAR EL SERVICIO [ " . $responseApi["message"] . " ]";
                        // informacion para construir log de respuesta y error
                        $data = [
                            "messageLog"    => "ERROR: NO SE PUDO MODIFICAR SERVICIO [ " . $responseApi["message"] . " ]",
                            "type"          => "SERVICIO",
                            "platform"      => "VO",
                            "state"         => FALSE,
                            "detail"        => "",
                            "input"         => $info["vo"]["serviceCode"],
                            "output"        => $estado,
                            "ws"            => "api/service",
                            "result"        => [
                                "estado" => $estado,
                                "estado_desc" => "NOOK"
                            ]
                        ];
                        $return = $this->_util->setResponse($vo, $data, $objSql);
                        // Almacena Log de seguimiento
                        $this->_util->setLog(
                            $this->_config->urlLog,
                            "API VO Servicio:ERROR",
                            "MEDIADOR",
                            $data
                        );
                    }
                } else {
                    // Se obtiene la respuesta de la causa del fallo en el WS
                    $responseApi = $vo->getResponseApi();
                    $estado = "NO SE PUDO CREAR EL ELEMENTO [ " . $responseApi["message"] . " ]";
                    // informacion para construir log de respuesta y error
                    $data = [
                        "messageLog" => "ERROR: NO PUDO CREAR ELEMENTO [ " . $responseApi["message"] . " ]",
                        "type"       => "ELEMENTO",
                        "platform"   => "VO",
                        "state"      => FALSE,
                        "detail"     => "",
                        "input"      => implode("_", $info["vo"]["series"]),
                        "output"     => $estado,
                        "ws"         => "api/element/",
                        "result"     => [
                            "estado" => $estado,
                            "estado_desc" => "NOOK"
                        ]
                    ];
                    $return = $this->_util->setResponse(
                        $vo,
                        $data,
                        $objSql
                    );
                    // Almacena Log de seguimiento
                    $this->_util->setLog(
                        $this->_config->urlLog,
                        "API VO Elementos:ERROR",
                        "MEDIADOR",
                        $data
                    );
                }
            } else {
                // Se obtiene la respuesta de la causa del fallo en el WS
                $responseApi = $vo->getResponseApi();
                $estado = "NO SE ENCONTRO UN CLIENTE VALIDO [ " . $responseApi["message"] . " ]";
                // informacion para construir log de respuesta y error
                $data = [
                    "messageLog"    => "ERROR: NO SE ENCONTRO UN CLIENTE [ " . $responseApi["message"] . " ]",
                    "type"          => "CLIENTE",
                    "platform"      => "VO",
                    "state"         => FALSE,
                    "detail"        => "",
                    "input"         => $info["vo"]["rut"],
                    "output"        => $estado,
                    "ws"            => "api/client/{rut}",
                    "result"        => [
                        "estado" => $estado,
                        "estado_desc" => "NOOK"
                    ]
                ];
                $return = $this->_util->setResponse($vo, $data, $objSql);
                // Almacena Log de seguimiento
                $this->_util->setLog(
                    $this->_config->urlLog,
                    "API VO Clientes:ERROR",
                    "MEDIADOR",
                    $data
                );
            }
        } else {
            // Se obtiene la respuesta de la causa del fallo en el WS
            $responseApi = $vo->getResponseApi();
            $estado = "PLANES ENVIADOS NO EXISTEN EN SISTEMA [ " . $responseApi["message"] . " ]";
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NO SE ENCONTRO UN PLAN [ " . $responseApi["message"] . " ]",
                "type"          => "PLAN",
                "platform"      => "VO",
                "state"         => FALSE,
                "detail"        => "",
                "input"         => implode("_", $info["vo"]["planes"]),
                "output"        => $estado,
                "ws"            => "api/plan/{code}",
                "result"        => [
                    "estado" => $estado,
                    "estado_desc" => "NOOK"
                ]
            ];
            $return = $this->_util->setResponse($vo, $data, $objSql);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "API VO Planes:ERROR",
                "MEDIADOR",
                $data
            );
        }
        return $return;
    }

    /**
     * setError
     *
     * @param  mixed $tipo
     * @param  mixed $detalle
     * @return void
     */
    public function setError($tipo, $detalle = "")
    {
        $error = [];
        switch ($tipo) {
            case "PARAMETROS":
                // Genera estructura de retorno de error
                $str = "SE ENCONTRARON DATOS VACIOS";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "DATA";
                $error["error"]["faultActor"]   = "DATOS NO VALIDOS";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "DATA_TV":
                // Genera estructura de retorno de error
                $str = "INSTANCIA DEBE SER DE TIPO TV";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "DATA";
                $error["error"]["faultActor"]   = "DATOS NO VALIDOS";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = "";
                break;
            case "DATA_PLANES":
                // Genera estructura de retorno de error
                $str = "NO ESTAN CORRECTOS LOS PLANES";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "DATA";
                $error["error"]["faultActor"]   = "DATOS NO VALIDOS";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = "";
                break;
            case "DATA_STBS":
                // Genera estructura de retorno de error
                $str = "TOTAL DE DECOS DEBE SER NUMERICO ENTRE 1 Y 9 INCLUSIVE";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "DATA";
                $error["error"]["faultActor"]   = "DATOS NO VALIDOS";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = "";
                break;
            case "OK":
                // Genera estructura de retorno de error VACIO
                $error["status"] = TRUE;
                $error["error"]["faultCode"]    = "";
                $error["error"]["faultActor"]   = "";
                $error["error"]["faultString"]  = "";
                $error["error"]["faultDetail"]  = "";
                break;
        }
        return $error;
    }

    /**
     * _validaConexionDb
     *
     * Esta función realiza la conexion a la base de datos disponible para el
     * Webservice, en caso que no se logre conexion no se continuara con nada
     * del proceso.
     *
     * @return bol
     */
    private function _validaConexionDb()
    {
        $return = TRUE;
        $db = new Db();
        $result =$db->connectionMediador();
        if($result === FALSE){
            $this->_return = $db->getError();
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Conexion Mediador:ERROR",
                "DB",
                $this->_return["default"]["error"]
            );
            $return = FALSE;
        } else {
            // Si la conexion se pudo establecer se asigna la instancia a la
            // clase Ws,para tener acceso a datos desde la clase
            $this->_dbMediador = $db->getConnection();
            $this->_sqlMediador = new Sql($this->_dbMediador);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Conexion Mediador:OK",
                "DB"
            );
            $return = TRUE;
        }
        return $return;
    }

    /**
     * _validaConexionDb
     *
     * Esta función realiza la conexion a la base de datos disponible para el
     * Webservice, en caso que no se logre conexion no se continuara con nada
     * del proceso.
     *
     * @return bol
     */
    private function _validaConexionDbMinerva()
    {
        $return = TRUE;
        $db = new Db();
        $result = $db->connectionMinerva();
        if ($result === FALSE) {
            $this->_return = $db->getError();
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Conexion Minerva:ERROR",
                "DB",
                $this->_return["default"]["error"]
            );
            $return = FALSE;
        } else {
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Conexion Minerva:OK",
                "DB"
            );
            // Si la conexion se pudo establecer se asigna la instancia a la
            // clase Ws,para tener acceso a datos desde la clase
            $this->_dbMinerva = $db->getConnection();
            $this->_sqlMinerva = new Sql($this->_dbMinerva);
            $return = TRUE;
        }
        return $return;
    }

    /**
     * _validaParametros
     *
     * Esta función realiza la validacion de los atributos de entrada, en caso
     * que al menos uno este vacio, la funcion retornara un TRUE, que indica
     * que encontro un error, por lo tanto no continuara el proceso
     *
     * @return bol
     */
    private function _validaParametros()
    {
        $retorno = TRUE;
        $detalle = [];
        foreach (get_object_vars($this) as $key => $value) {
            $campo = substr($key, 1);
            if (array_key_exists($campo, $this->_info)) {
                if (empty($value)) {
                    $detalle[] = "El argumento [". $campo."] es vacio";
                    $retorno = FALSE;
                }
            }
        }
        // Si al menos un parametro viene vacio se retorna un error
        if ($retorno === FALSE) {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: LOS PARAMETROS NO SON CORRECTOS",
                "type"          => "PARAMETROS",
                "detail"        => $detalle,
            ];
            $this->_return = $this->_util->setResponse($this, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de parametros:ERROR",
                "PARAMETROS",
                $data
            );
        }else{
            // Se valida que la instancia sea para Television
            $retorno = $this->_validaInstancia();
            // Si no genero error, pasa a la siguiente validacion
            if ($retorno == TRUE) {
                // Se valida que los planes esten correctos
                $retorno = $this->_validaPlanes();
                // Si no genero error, pasa a la siguiente validacion
                if ($retorno == TRUE) {
                    // Se valida que el numero de decos sea correcto
                    $retorno = $this->_validaStbs();
                }
            }

        }
        if($retorno === TRUE){
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de parametros:OK",
                "PARAMETROS"
            );
        }
        return $retorno;
    }

    /**
     * _validaInstancia
     *
     * @return void
     */
    private function _validaInstancia()
    {
        $retorno = TRUE;
        // Se valida que la instancia sea para Television
        $resultado = $this->_util->splitAccount($this->_instanciaSubCuenta);
        if (!strstr($resultado["instancia"], "TV")) {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NO ES INSTANCIA DE TV",
                "type"          => "DATA_TV"
            ];
            $this->_return = $this->_util->setResponse($this, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de instancia:ERROR",
                "PARAMETROS",
                $data
            );
            $retorno = FALSE;
        }

        return $retorno;
    }

    /**
     * _validaPlanes
     *
     * @return void
     */
    private function _validaPlanes()
    {
        $retorno = TRUE;
        // Se valida que los planes vengan correctamente
         // Prepara planes
        $planes = $this->_util->parseData($this->_planes, ",");
        // Se carga la posicion 2, la cual es la que trae los planes
        $strPlanes = $planes[2];
        $vecPlanes = $this->_util->parseData($strPlanes, "|");
        $planesMinerva  = [];
        $planesVo       = [];
        foreach ($vecPlanes as $key => $value) {
            //$plaPlanes = $this->_util->parseData($value, "-");
            // Se verifica que los planes hayan sido enviados para los
            // dos plataformas, Minerva y Vo, por lo tanto deben existir dos
            // posiciones en el vector plaPlanes
            // La posición 0 trae el codigo del plan de Minerva
            // La posicion 1 trae el codigo del plan de VO
            //if(is_array($plaPlanes) && count($plaPlanes)==2){
                $planesMinerva[] = $value;
                $planesVo[]      = $value;
            //}
        }
        if(!((count($planesMinerva) === count($planesVo)
         && count($planesMinerva)>0))){
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NO ESTA CORRECTO LOS PLANES",
                "type"          => "DATA_PLANES"
            ];
            $this->_return = $this->_util->setResponse($this, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de planes:ERROR",
                "PARAMETROS",
                $data
            );
            $retorno = FALSE;
        }

        return $retorno;
    }

    /**
     * _validaStbs
     *
     * @return void
     */
    private function _validaStbs()
    {
        $retorno = TRUE;
        // Se valida que el valor de stbs sea numerico
        if (!is_numeric($this->_totalDecosCliente)
            || ($this->_totalDecosCliente <= 0 || $this->_totalDecosCliente >= 9)) {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NUMERO DE DECOS INCORRECTO",
                "type"          => "DATA_STBS"
            ];
            $this->_return = $this->_util->setResponse($this, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de DECOS:ERROR",
                "PARAMETROS",
                $data
            );
            $retorno = FALSE;
        }

        return $retorno;
    }
}

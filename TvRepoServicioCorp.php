<?php

/**
 * Class Reposicion de Servicio de Televisión
 *
 * Esta clase permite realizar el proceso de reposicion de Servicio para
 * Minerva y VO
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
 * TvRepoServicio
 */
class TvRepoServicio
{
    private $_instancia;
    private $_rut;
    private $_planes;
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
        $this->_instancia  = $info["instancia"];
        $this->_rut        = $info["rut"];
        $this->_planes     = $info["planes"];
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
     * repoServicio
     *
     * Esta función genera el Repo de servicio para las plataformas definidas
     *
     * @return struct
     */
    public function repoServicio()
    {
        $return = [];
        // Almacena Log de seguimiento
        $this->_util->setLog(
            $this->_config->urlLog,
            "R E P O S I C I O N   D E   S E R V I C I O",
            "MEDIADOR",
            "Inicia proceso repo de servicio en Minerva y Vo",
            "INICIO",
        );
        // Se valida si existe conexion con base de datos
        $validaDb        = $this->_validaConexionDb();
        $validaDbMinerva = $this->_validaConexionDbMinerva();
        if ($validaDb && $validaDbMinerva) {
            // Se registra transaccion al iniciar proceso
            $this->_idConsulta = $this->_util->startTransaction(
                "repo_servicio",
                $this->_sqlMediador,
                $this->_info,
            );
            // Se valida si los parametros traen la información correcta
            $validaParametros = $this->_validaParametros();
            if ($validaParametros === TRUE) {
                // Se invoca el proceso de repo para un servicio en Minerva
                // un servicio en Minerva
                $return = $this->_repoServicioMinerva();
                $this->_return["default"] = $return["default"];
                $this->_return["minerva"] = $return["minerva"];
                if ($this->_return["default"]["status"] === FALSE) {
                    return $this->_return;
                }

                // Se invoca el proceso de repo para un servicio en VO
                $return = $this->_repoServicioVO();
                $this->_return["default"] = $return["default"];
                $this->_return["vo"]      = $return["vo"];
                if ($this->_return["default"]["status"] === FALSE) {
                    return $this->_return;
                }

                // Se evalua si ocurrio alguna excepcion
                if ($this->_return["default"]["status"] !== FALSE) {
                    // Se registra transaccion al finalizar proceso
                    $this->_idConsulta = $this->_util->endTransaction(
                        "respuesta_repo_servicio",
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
                        "C O R T E   D E   S E R V I C I O",
                        "MEDIADOR",
                        "Finaliza proceso de repo servicio en Minerva y Vo",
                        "FIN",
                    );
                }
            }
        }
        return $this->_return;
    }

    /**
     * _repoServicioMinerva
     *
     * Esta función genera el Repo de servicio en la plataforma Minerma
     *
     * @return void
     */
    private function _repoServicioMinerva()
    {
        $return = [];
        $ws = new Ws();
        // Se envia la instancia de la base de datos del mediador a Minerva
        $ws->setInstanceDb($this->_dbMediador);
        // Se envia la instancia de la base de datos de minerva a Minerva
        $ws->setInstanceDbMinerva($this->_dbMinerva);
        // Verifica que la plataforma Minerva este activa para repo
        $rsPlataforma = $this->_sqlMediador->getPlatform("Minerva");
        // Verifica que la plataforma Vo este activa para repo
        if ($rsPlataforma === TRUE) {
            // Obtiene la Url del Ws a ser invocado
            $urlWs =  $this->_sqlMediador->getUrlWs("ACTIVA_SERVICIO");
            $rsVAlidaUrl = $this->_util->validateUrl($urlWs);
            // Valida si la URL del WS de Minerva esta respondiendo
            if ($rsVAlidaUrl === TRUE) {
                // Prepara los datos para repo de Minerva
                $validaPrepara = $this->_prepararDatosMinerva();
                if ($validaPrepara) {
                    // Procesa Repo de servicio Minerva
                    // La respuesta del proceso de repo es almacena en
                    // $this->_return tanto la respuesta del proceso como la
                    // respuesta del error, en caso de existir
                    $return = $this->setRepoServicioMinerva($ws, $this->_aux);
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
     * _repoServicioVo
     *
     * Esta función genera el Repo de servicio en la plataforma VO
     *
     * @return strunct
     */
    private function _repoServicioVo()
    {
        $return = [];
        $vo = new Vo();
        // Se envia la instancia de la base de datos del mediador a VO
        $vo->setInstanceDb($this->_dbMediador);
        $rsPlataforma = $this->_sqlMediador->getPlatform("Vo");
        // Verifica que la plataforma Vo este activa para repo
        if ($rsPlataforma === TRUE) {
            $rsVAlidaUrl = $this->_util->validateUrl($this->_config->urlVo);
            // Valida si la URL del WS de VO esta respondiendo
            if ($rsVAlidaUrl === TRUE) {
                // Obtiene el token para conexxion con el WS
                $rsToken = $vo->setToken();
                // Valida que el token se haya generado
                if($rsToken === TRUE) {
                    // Prepara los datos para repo de VO
                    $validaPrepara = $this->_prepararDatosVo();
                    if ($validaPrepara) {
                        // Procesa Repo servicio VO
                        $return = $this->_setRepoServicioVo(
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
        // Prepara Instancia
        $instancia = $this->_instancia;
        $this->_aux["minerva"]["instancia"]     = $instancia;
        //Prepara Rut
        $rut = $this->_rut;
        $this->_aux["minerva"]["rut"]           = $rut;
        //Prepara Planes
        $planes = $this->_planes;
        $this->_aux["minerva"]["planes"]        = $planes;
        // Prepara estructura de consumo de WS para
        // servicio de repo  en Minerva
        $prefijo = $this->_config->prefix;
        $parametros = array("cCustomerId" => $prefijo . $instancia);
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
        // Prepara instancia
        $instancia  = $this->_instancia;
        $prefijo = $this->_config->prefix;
        $this->_aux["vo"]["serviceCode"] = $prefijo . $instancia;

        // Prepara rut
        $rut = $this->_rut;
        $this->_aux["vo"]["rut"]     = $rut;

        // Prepara planes
        $planes = $this->_planes;
        $this->_aux["vo"]["planes"]  = $planes;

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
     * setRepoServicioMinerva
     *
     * @param  mixed $ws
     * @param  mixed $info
     * @return void
     */
    public function setRepoServicioMinerva($ws, $info)
    {
        $return         = [];
        $objSql         = $this->_sqlMediador;
        $parametros     = $info["minerva"]["parametros"];
        $instancia      = $info["minerva"]["instancia"];
        $prefijo        = $this->_config->prefix;

        $rsPlanes = $ws->getPlanesMinervaByCustomerId($prefijo . $instancia);
        if($rsPlanes !== -1) {
            // Consumo del servicio de Repo de Minerva
            $result = $ws->consumeWs(
                "ACTIVA_SERVICIO",
                "TraeRegistros",
                $parametros
            );
            //Simular respuesta

            // INICIO DE SIMULACION
            //$result = "respuesta";    // Valida respuesta pero no valida
            //$result[0]["estado"] = 5; // Valida la respuesta pero no  estado
            //$result[0]["estado"] = 0; // Se simula que la respuesta es valida
            //$exitosM = 2;             // Se simula que se adicionaron 2 decos
            // FIN DE SIMULACION

            // Valida el consumo del WS
            if ($result) {
                error_log("Repo en Minerva");
                if (is_array($result)) {
                    error_log("conexion repo_servicio");
                    if ($result[0]["estado"] == 0
                     || $result[0]["estado"] == 1002) {
                        $estado = "REPOSICION DE SERVICIO CORRECTO";
                        // informacion para  log de respuesta y error
                        $data = [
                            "messageLog"    => "OK:PROCESO CORRECTO",
                            "type"          => "OK",
                            "platform"      => "MINERVA",
                            "state"         => TRUE,
                            "detail"        => "",
                            "input"         => implode("_", $parametros),
                            "output"        => $estado,
                            "ws"            => "ACTIVA_SERVICIO",
                            "result"        => [
                                "estado" => $estado,
                                "estado_desc" => "OK"
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
                            "Repo Servicio Minerva:OK",
                            "MEDIADOR"
                        );
                    } else {
                        $estado = "WS REPO ESTADO DE RESPUESTA NO ES CORRECTO";
                        // informacion para construir log de respuesta y error
                        $data = [
                            "messageLog"    => "ERROR: WS REPO ESTADO NO OK",
                            "type"          => "ESTADO",
                            "platform"      => "MINERVA",
                            "state"         => FALSE,
                            "detail"        => "",
                            "input"         => implode("_", $parametros),
                            "output"        => $estado,
                            "ws"            => "ACTIVA_SERVICIO",
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
                            "WS Minerva Estado:ERROR",
                            "MEDIADOR",
                            $data
                        );
                    }
                    error_log("Resultado de repo_servicio" . $estado);
                } else {
                    $estado = "RESPUESTA DE WS REPOSICION NO ES CORRECTA";
                    // informacion para construir log de respuesta y error
                    $data = [
                        "messageLog"    => "ERROR: WS REPO RESPONDE INCORRECTO",
                        "type"          => "RESPUESTA",
                        "platform"      => "MINERVA",
                        "state"         => FALSE,
                        "detail"        => "",
                        "input"         => implode("_", $parametros),
                        "output"        => $estado,
                        "ws"            => "ACTIVA_SERVICIO",
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
                    "messageLog"    => "ERROR: FALLO CONSUMO WS REPO SERVICIO",
                    "type"          => "CONEXION",
                    "platform"      => "MINERVA",
                    "state"         => FALSE,
                    "detail"        => "",
                    "input"         => implode("_", $parametros),
                    "output"        => $estado,
                    "ws"            => "ACTIVA_SERVICIO",
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
        } else {
            $estado = "NO EXISTE INSTANCIA EN MINERVA";
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NO EXISTE INSTANCIA EN MINERVA",
                "type"          => "DATA",
                "platform"      => "MINERVA",
                "state"         => FALSE,
                "detail"        => "",
                "input"         => implode("_", $parametros),
                "output"        => $estado,
                "ws"            => "INFO_N_SERVICIO_V2",
                "result"        => [
                    "estado" => $estado,
                    "estado_desc" => "NOOK"
                ]
            ];
            $return = $this->_util->setResponse($ws, $data, $objSql);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "WS Minerva Instancia:ERROR",
                "MEDIADOR",
                $data
            );
        }
        return $return;
    }

    /**
     * _setRepoServicioVo
     *
     * @param  mixed $info
     * @param  mixed $info
     * @return void
     */
    private function _setRepoServicioVo($vo, $info)
    {
        $return   = [];
        $objSql = $this->_sqlMediador;
        // Se verifica que el servicio exista, en caso que no exista se
        // retorna true, si existe el servicio ser  envia a eliminar,
        // solo si el proceso retorna TRUE se continua
        $rsServicio = $vo->resumeServicio($info);
        if ($rsServicio === TRUE) {
            //Respuesta OK
            $estado = "REPOSICION DE SERVICIO CORRECTO";
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog" => "OK: PROCESO CORRECTO",
                "type"       => "OK",
                "platform"   => "VO",
                "state"      => TRUE,
                "detail"     => "",
                "input"      => $info["vo"]["serviceCode"],
                "output"     => $estado,
                "ws"         => "api/service/{servicecode}/resume",
                "result"     => [
                    "estado" => $estado,
                    "estado_desc" => "OK"
                ]
            ];
            $return = $this->_util->setResponse($vo, $data, $objSql);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Repo Servicio VO:OK",
                "MEDIADOR",
                $data
            );
        } else {
            $estado = "NO SE PUDO ACTIVAR EL SERVICIO";
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NO SE PUDO ACTIVAR EL SERVICIO",
                "type"          => "SERVICIO",
                "platform"      => "VO",
                "state"         => FALSE,
                "detail"        => "",
                "input"         => $info["vo"]["serviceCode"],
                "output"        => $estado,
                "ws"            => "api/service{servicecode}",
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
            // S i la conexion se pudo establecer se asigna la instancia a la
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
            // Si la conexion se pudo establecer se asigna la instancia a la
            // clase Ws,para tener acceso a datos desde la clase
            $this->_dbMinerva = $db->getConnection();
            $this->_sqlMinerva = new Sql($this->_dbMinerva);
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
        }
        if ($retorno === TRUE) {
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
        if (!strstr($this->_instancia, "TV")) {
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
}

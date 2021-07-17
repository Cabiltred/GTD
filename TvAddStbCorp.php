<?php

/**
 * Class Alta de deco de Servicio de Televisión
 *
 * Esta clase permite realizar el proceso de Alta de Deco de un
 * Servicio para Minerva y VO
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
 * TvBajaServicio
 */
class TvAddStbCorp
{
    private $_instancia;
    private $_mac;
    private $_serie;
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
        $this->_instancia   = $info["instancia"];
        $this->_mac         = $info["mac"];
        $this->_serie       = $info["serie"];
        $this->_info        = $info;
        $this->_aux         = [];
        $this->_dbMediador  = FALSE;
        $this->_dbMinerva   = FALSE;

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
     * bajaServicio
     *
     * Esta función genera el Baja de servicio para las plataformas definidas
     *
     * @return struct
     */
    public function addStb()
    {
        $return = [];
        // Almacena Log de seguimiento
        $this->_util->setLog(
            $this->_config->urlLog,
            "A L T A   D E   D E C O   C O R P",
            "MEDIADOR",
            "Inicia proceso alta de servicio en Minerva y Vo",
            "INICIO",
        );
        // Se valida si existe conexion con base de datos
        $validaDb        = $this->_validaConexionDb();
        $validaDbMinerva = $this->_validaConexionDbMinerva();
        if ($validaDb && $validaDbMinerva) {
            // Se registra transaccion al iniciar proceso
            $this->_idConsulta = $this->_util->startTransaction(
                "add_stb_corp",
                $this->_sqlMediador,
                $this->_info,
            );
            // Se valida si los parametros traen la información correcta
            $validaParametros = $this->_validaParametros();
            if ($validaParametros === TRUE) {
                // Se invoca el proceso que da de alta
                // un servicio en Minerva
                $return = $this->_addStbMinerva();
                $this->_return["default"] = $return["default"];
                $this->_return["minerva"] = $return["minerva"];
                if ($this->_return["default"]["status"] === FALSE) {
                    return $this->_return;
                }

                // Se invoca el proceso que da de baja un servicio en VO
                $return = $this->_addStbVO();
                $this->_return["default"] = $return["default"];
                $this->_return["vo"]      = $return["vo"];
                if ($this->_return["default"]["status"] === FALSE) {
                    return $this->_return;
                }

                // Se evalua si ocurrio alguna excepcion
                if ($this->_return["default"]["status"] !== FALSE) {
                    // Se registra transaccion al finalizar proceso
                    $this->_idConsulta = $this->_util->endTransaction(
                        "respuesta_add_stb_corp",
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
                        "A L T A   D E   D E C O   S E R V I C I O   C O R P",
                        "MEDIADOR",
                        "Finaliza proceso de baja deco servicio en Minerva y Vo",
                        "FIN",
                    );
                }
            }
        }
        return $this->_return;
    }

    /**
     * _bajaServicioMinerva
     *
     * Esta función genera el Baja de servicio en la plataforma Minerma
     *
     * @return void
     */
    private function _addStbMinerva()
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
            $urlWs =  $this->_sqlMediador->getUrlWs("INFO_N_SERVICIO_V2");
            $rsVAlidaUrl = $this->_util->validateUrl($urlWs);
            // Valida si la URL del WS de Minerva esta respondiendo
            if ($rsVAlidaUrl === TRUE) {
                // Prepara los datos para Aprovisionamiento de Minerva
                $validaPrepara = $this->_prepararDatosMinerva();
                if ($validaPrepara) {
                    // Procesa Baja de servicio Minerva
                    // La respuesta del proceso de baja es almacena en
                    // $this->_return tanto la respuesta del proceso como la
                    // respuesta del error, en caso de existir
                    $return = $this->setAddStbMinerva($ws, $this->_aux);
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
     * _bajaServicioVo
     *
     * Esta función genera el Baja de servicio en la plataforma VO
     *
     * @return strunct
     */
    private function _addStbVo()
    {
        $return = [];
        $vo = new Vo();
        // Se envia la instancia de la base de datos del mediador a VO
        $vo->setInstanceDb($this->_dbMediador);
        $rsPlataforma = $this->_sqlMediador->getPlatform("Vo");
        // Verifica que la plataforma Vo este activa para aprovisionamiento
        if ($rsPlataforma === TRUE) {
            $rsVAlidaUrl = $this->_util->validateUrl($this->_config->urlVo);
            // Valida si la URL del WS de VO esta respondiendo
            if ($rsVAlidaUrl === TRUE) {
                // Obtiene el token para conexxion con el WS
                $rsToken = $vo->setToken();
                // Valida que el token se haya generado
                if($rsToken === TRUE) {
                    // Prepara los datos para Aprovisionamiento de VO
                    $validaPrepara = $this->_prepararDatosVo();
                    if ($validaPrepara) {
                        // Procesa Baja servicio VO
                        $return = $this->_setAddStbVo(
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
        $instancia  = $this->_instancia;
        $this->_aux["minerva"]["instancia"]     = $instancia;

        // Prepara mac
        $mac = $this->_mac;
        $this->_aux["minerva"]["mac"] = $mac;

        // Prepara serie
        $serie = $this->_serie;
        $this->_aux["minerva"]["serie"] = $serie;
        // Prepara estructura de consumo de WS para aprovisionamiento
        // del servicio de baja  en Minerva
        $parametros = array("cCustomerId" => $instancia);
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
        $this->_aux["vo"]["serviceCode"] = $instancia;

        // Prepara mac
        $mac = $this->_mac;
        $this->_aux["vo"]["mac"] = $mac;

        // Prepara serie
        $serie = $this->_serie;
        $this->_aux["vo"]["serie"] = $serie;

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
     * setBajaServicioMinerva
     *
     * @param  mixed $ws
     * @param  mixed $info
     * @return void
     */
    public function setAddStbMinerva($ws, $info)
    {
        $return         = [];
        $objSql         = $this->_sqlMediador;
        $parametros     = $info["minerva"]["parametros"];
        $mac            = $info["minerva"]["mac"];
        // Consumo del servicio de consulta de Minerva
        $result = $ws->consumeWs(
            "INFO_N_SERVICIO_V2",
            "TraeRegistros",
            $parametros
        );
        // Valida el consumo del WS
        if ($result) {
            error_log("Elinacion de deco en Minerva");
            if (is_array($result)) {
                error_log("conexion del_stb");
                if ($result[0]["estado"] == 0
                    || $result[0]["estado"] == 1002) {
                    // Si se encontro informacion de la mac
                    $macs = explode(";", $result[0]['cMac']);
                    $exist = 0;
                    foreach ($macs as $a) {
                        $stb = explode("=", $a);
                        $mac_minerva = trim($stb[1]);
                        if ($mac_minerva == $mac) {
                            $exist = 1;
                            break;
                        }
                    }
                    if (!$exist) {
                        $estado = "WS DEL_STB MAC NO EXISTE";
                        // informacion para log de respuesta y error
                        $data = [
                            "messageLog"    => "ERROR: WS DEL_STB MAC NO EXISTE",
                            "type"          => "DECO",
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
                        $return = $this->_util->setResponse(
                            $ws,
                            $data,
                            $objSql
                        );
                        // Almacena Log de seguimiento
                        $this->_util->setLog(
                            $this->_config->urlLog,
                            "WS Minerva Decos:ERROR",
                            "MEDIADOR",
                            $data
                        );
                    }

                    $parametersDel = array('cMacAddress' => $mac);
                    // Consumo del servicio de eliminacion de stb de Minerva
                    $resultAdd = $ws->consumeWs(
                        "ELIMINA_STB",
                        "TraeRegistros",
                        $parametersDel
                    );
                    if ($resultAdd) {
                        if (is_array($resultAdd)) {
                            if ($resultAdd[0]["estado"] == 0 || $resultAdd[0]["estado"] == 1002) {
                                $estado = "ELIMIANCION DE STB DE SERVICIO CORRECTA";
                                // informacion para  log de respuesta y error
                                $data = [
                                    "messageLog"    => "OK:PROCESO CORRECTO",
                                    "type"          => "OK",
                                    "platform"      => "MINERVA",
                                    "state"         => TRUE,
                                    "detail"        => "",
                                    "input"         => implode("_", $parametros),
                                    "output"        => $estado,
                                    "ws"            => "DEL_STB_CORP",
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
                                    "Elimiancion de Deco Minerva:OK",
                                    "MEDIADOR"
                                );
                            } else {
                                $estado = "WS ELIMINA_STB ESTADO DE RESPUESTA NO ES CORRECTO";
                                // informacion para construir log de respuesta y error
                                $data = [
                                    "messageLog"    => "ERROR: WS ELIMINA_STB ESTADO NO OK",
                                    "type"          => "ESTADO",
                                    "platform"      => "MINERVA",
                                    "state"         => FALSE,
                                    "detail"        => "",
                                    "input"         => implode("_", $parametros),
                                    "output"        => $estado,
                                    "ws"            => "ELIMINA_STB",
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
                        } else {
                            $estado = "RESPUESTA DE WS INFO_N_SERVICIO_V2 NO ES CORRECTA";
                            // informacion para construir log de respuesta y error
                            $data = [
                                "messageLog"    => "ERROR: WS ELIMINA_STB RESPONDE INCORRECTO",
                                "type"          => "RESPUESTA",
                                "platform"      => "MINERVA",
                                "state"         => FALSE,
                                "detail"        => "",
                                "input"         => implode("_", $parametros),
                                "output"        => $estado,
                                "ws"            => "ELIMINA_STB",
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
                    } else {
                        $estado = "SIN CONEXION";
                        // informacion para construir log de respuesta y error
                        $data = [
                            "messageLog"    => "ERROR: FALLO CONSUMO WS INFO_N_SERVICIO_V2",
                            "type"          => "CONEXION",
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
                            "WS Minerva:ERROR",
                            "MEDIADOR",
                            $data
                        );
                    }
                } else {
                    $estado = "WS INFO_N_SERVICIO_V2 ESTADO DE RESPUESTA NO ES CORRECTO";
                    // informacion para construir log de respuesta y error
                    $data = [
                        "messageLog"    => "ERROR: WS INFO_N_SERVICIO_V2 ESTADO NO OK",
                        "type"          => "ESTADO",
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
                error_log("Resultado de baja_servicio" . $estado);
            } else {
                $estado = "RESPUESTA DE WS INFO_N_SERVICIO_V2 NO ES CORRECTA";
                // informacion para construir log de respuesta y error
                $data = [
                    "messageLog"    => "ERROR: WS INFO_N_SERVICIO_V2 RESPONDE INCORRECTO",
                    "type"          => "RESPUESTA",
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
                "messageLog"    => "ERROR: FALLO CONSUMO WS INFO_N_SERVICIO_V2",
                "type"          => "CONEXION",
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
                "WS Minerva:ERROR",
                "MEDIADOR",
                $data
            );
        }

        return $return;
    }

    /**
     * _setBajaServicioVo
     *
     * @param  mixed $info
     * @param  mixed $info
     * @return void
     */
    private function _setAddStbVo($vo, $info)
    {
        $return   = [];
        $objSql = $this->_sqlMediador;
        // Se verifica que el elemento exista, en caso que no exista se
        // retorna true, si existe el elemento se envia a eliminar,
        // solo si el proceso retorna TRUE se continua
        $rsElemento = $vo->dropElemento($info);
        if ($rsElemento === TRUE) {
            //Respuesta OK
            $estado = "BAJA DE ELEMENTO CORRECTA";
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog" => "OK: PROCESO CORRECTO",
                "type"       => "OK",
                "platform"   => "VO",
                "state"      => TRUE,
                "detail"     => "",
                "input"      => $info["vo"]["mac"],
                "output"     => $estado,
                "ws"         => "api/element/{serial}",
                "result"     => [
                    "estado" => $estado,
                    "estado_desc" => "OK"
                ]
            ];
            $return = $this->_util->setResponse($vo, $data, $objSql);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Elimnacion de elemento VO:OK",
                "MEDIADOR",
                $data
            );
        } else {
            // Se obtiene la respuesta de la causa del fallo en el WS
            $responseApi = $vo->getResponseApi();
            $estado = "NO SE PUDO ELIMINAR EL ELEMENTO [ " . $responseApi["message"] . " ]";
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NO SE PUDO ELIMINAR EL ELEMENTO [ " . $responseApi["message"] . " ]",
                "type"          => "ELEMENTO",
                "platform"      => "VO",
                "state"         => FALSE,
                "detail"        => "",
                "input"         => $info["vo"]["mac"],
                "output"        => $estado,
                "ws"            => "api/service{serial}",
                "result"        => [
                    "estado" => $estado,
                    "estado_desc" => "NOOK"
                ]
            ];
            $return = $this->_util->setResponse($vo, $data, $objSql);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "API VO Elemento:ERROR",
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
            case "DATA_MACS":
                // Genera estructura de retorno de error
                $str = "NO ESTAN CORRECTA LA MAC";
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
            // Si no genero error, pasa a la siguiente validacion
            if ($retorno == TRUE) {
                // Se valida que la mac este correcta
                $retorno = $this->_validaMac();
            }
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
        $instancia = $this->_instancia;
        if (substr($instancia, 0, 3) != "GTD"
            && substr($instancia, 0, 2) != "TV") {
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
     * _validaMac
     *
     * @return void
     */
    private function _validaMac()
    {
        $retorno = TRUE;
        // Se valida que la instancia sea para Television
        $mac = $this->_mac;
        // Solo letras de la A a la F(mayusculas o minusculas),
        // numeros del 0 al 9 y de largo 12,
        if (!preg_match('/^[A-Fa-f0-9]{12}$/i', $mac)) {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NO ES VALIDA LA MAC",
                "type"          => "DATA_MACS"
            ];
            $this->_return = $this->_util->setResponse($this, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de mac:ERROR",
                "PARAMETROS",
                $data
            );
            $retorno = FALSE;
        }

        return $retorno;
    }
}

<?php

/**
 * Class Vo
 *
 * Esta clase permite hacer todos los procesos de servicio contra el sistema VO
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
use Gtd\ClasesTransversal\Sql;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use kamermans\OAuth2\GrantType\ClientCredentials;
use kamermans\OAuth2\OAuth2Middleware;

/**
 *
 * Vo
 */
class Vo
{

    private $_config;
    private $_dbMediador;      // Instancia para base de datos Mediador
    private $_http;
    private $_response;       // Almacena la respuesta del ultimo Ws consumido
    private $_accessToken;
    public  $_sqlMediador;    // Instancia clase procesos Sql

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        // Se instancia la clase de configuración.
        $this->_config      = new Config();
        // Instancia DB se inicia false
        $this->_wsdb          = FALSE;
        // Instancia clase Procesos Sql
        $this->_sqlMediador   = FALSE;
        // Inicialize el token en vacio
        $this->_accessToken   = "";
    }

    /**
     * _getToken
     *
     * @return void
     */
    private function _getToken()
    {
        // Solicita el token de acceso al api, utilizando las llaves
        // cliente_id y client_secret, a traves de OAUTH2

        // se configura la url para la solicitud del token
        $baseUri = $this->_config->urlApiVo . $this->_config->urlTokenVo;
        // Se instancia la clase con la URL de solicitud del token
        $reauth_client = new Client([
            // URL para la solicituid del token
            'base_uri' => $baseUri,
        ]);
        // Se crean las credenciales de conexion para generacion de token
        $reauth_config = [
            "client_id" => $this->_config->clientIdVo,
            "client_secret" => $this->_config->clientSecretVo,
        ];
        // Se setean las credenciales para autenticacion
        $grant_type = new ClientCredentials($reauth_client, $reauth_config);
        $oauth = new OAuth2Middleware($grant_type);
        // Se cargan las credenciales generadas a la estructura del token
        $stack = HandlerStack::create();
        $stack->push($oauth);
        // Se obtiene el token generado
        $token = $oauth->getAccessToken();
        return $token;
    }

    /**
     * _getHttp
     *
     * @return void
     */
    private function _getHttp()
    {
        // Se instancia el API pasando el token de oAuth2
        $baseUri = $this->_config->urlApiVo;
        $http = new Client([
            // URL for access_token request
            'base_uri' => $baseUri,
            'headers' => [
                'Authorization' => "Bearer " . $this->_accessToken,
                'Content-Type'   => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
        // Se retorna la instancia del API, para ser usada por los metodos
        return $http;
    }



    /**
     * createCliente
     *
     * @param  mixed $info
     *
     * @return void
     */
    public function createCliente($info)
    {
        $retorno = FALSE;
        try {
            $url = "/api/client";
            $data = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'form_params' => [
                    'rut'     => $info["vo"]["rut"],
                    'name'    => $info["vo"]["name"],
                    'address' => $info["vo"]["address"],
                    'phone'   => $info["vo"]["phone"],
                    'comment' => $info["vo"]["comment"]
                ]
            ];
            // Se consume el metodo de creacion de Elemento
            $response = $this->_http->request("POST", $url, $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 201) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }

        return $retorno;
    }

    /**
     * createElemento
     *
     * @param  mixed $info
     * @param  mixed $stb
     * @param  mixed $key
     * @param  mixed $serie
     *
     * @return void
     */
    public function createElemento($info, $stb, $key, $serie)
    {
        $retorno = FALSE;
        // Se obtiene el prefijo de la serie para obtener modelo y marca
        $prefijo = $info["vo"]["prefijoSeries"][$serie];
        $tipo = $this->_config->elementTypeVo;
        $compania = $this->_config->tenantVo;
        try {
            $url = "/api/element";
            $data = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'json' => [
                    'serial'       => $info["vo"]["macs"][$key],
                    'name'         => $info["vo"]["series"][$key],
                    'manufacturer' => $stb[$prefijo]["marca"],
                    'model'        => $stb[$prefijo]["modelo"],
                    'elementType'  => [
                        $tipo => $tipo
                    ],
                    'tenant'       => [
                        $compania => $compania
                    ]
                ]
            ];
            // Se consume el metodo de creacion de Elemento
            $response = $this->_http->request("POST", $url, $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 201) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }

        return $retorno;
    }

    /**
     * createServicio
     *
     * @param  mixed $info
     * @return void
     */
    public function createServicio($info)
    {
        $retorno = FALSE;
        $planes = $info["vo"]["planes"];
        // Se elimina la primera posicion para los package
        unset($planes[0]);
        // Se unifican los planes en un string separado por comas
        $package = implode(",", $planes);
        try {
            $url = "/api/service";
            $data = [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'type'          => $this->_config->typeVo,
                    'serviceid'     => $info["vo"]["serviceId"],
                    'servicecode'   => $info["vo"]["serviceCode"],
                    'rut'           => $info["vo"]["rut"],
                    'packetcode'    => $info["vo"]["planes"][0],
                    'elements'      => $info["vo"]["macs"],
                    'parameters'    => [
                        [
                            "name"  => "caller_id_number",
                            "value" => ""
                        ],
                        [
                            "name"  => "additional_packages",
                            "value" => $package
                        ],
                        [
                            "name"  => "region",
                            "value" => $info["vo"]["codigoRegion"]
                        ],
                        [
                            "name"  => "amount_stb",
                            "value" => $info["vo"]["stbs"]
                        ],
                        [
                            "name"  => "service_order",
                            "value" => $info["vo"]["serviceOrder"]
                        ],
                        [
                            "name"  => "tipo_acceso",
                            "value" => $this->_config->accessTypeVo
                        ],
                        [
                            "name"  => "ftth_port",
                            "value" => $info["vo"]["ftthPort"]
                        ],
                        [
                            "name"  => "ftth_nodo",
                            "value" => $info["vo"]["ftthNode"]
                        ],
                        [
                            "name"  => "ftth_equipo",
                            "value" => $info["vo"]["ftthEquipo"]
                        ],
                        [
                            "name"  => "compania",
                            "value" => $this->_config->tenantVo
                        ],
                        [
                            "name"  => "provision",
                            "value" => $this->_config->provisionVo
                        ],
                        [
                            "name"  => "tv_technology",
                            "value" => $this->_config->technologyTvVo
                        ],
                        [
                            "name"  => "tipo_servicio",
                            "value" => $this->_config->serviceTypeVo
                        ],
                        [
                            "name" => "client_name",
                            "value" => $info["vo"]["name"]
                        ],
                    ],
                ]
            ];
            // Se consume el metodo de creacion de Elemento
            $response = $this->_http->request("POST", $url, $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 201) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }

        return $retorno;
    }

    /**
     * createServicioCorp
     *
     * @param  mixed $info
     * @return void
     */
    public function createServicioCorp($info)
    {
        $retorno = FALSE;
        $planes = $info["vo"]["planes"];
        // Se elimina la primera posicion para los package
        unset($planes[0]);
        // Se unifican los planes en un string separado por comas
        $package = implode(",", $planes);
        if(trim($package) === ""){
            $package = $info["vo"]["planes"][0];
        }
        try {
            $url = "/api/service";
            $data = [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'type'          => $this->_config->typeVo,
                    'serviceid'     => $info["vo"]["serviceId"],
                    'servicecode'   => $info["vo"]["serviceCode"],
                    'rut'           => $info["vo"]["rut"],
                    'packetcode'    => $info["vo"]["planes"][0],
                    'elements'      => "",
                    'parameters'    => [
                        [
                            "name"  => "caller_id_number",
                            "value" => ""
                        ],
                        [
                            "name"  => "additional_packages",
                            "value" => $package
                        ],
                        [
                            "name"  => "region",
                            "value" => $info["vo"]["codigoRegion"]
                        ],
                        [
                            "name"  => "amount_stb",
                            "value" => $info["vo"]["stbs"]
                        ],
                        [
                            "name"  => "service_order",
                            "value" => $info["vo"]["serviceOrder"]
                        ],
                        [
                            "name"  => "compania",
                            "value" => $this->_config->tenantVo
                        ],
                        [
                            "name"  => "provision",
                            "value" => $this->_config->provisionVo
                        ],
                        [
                            "name"  => "tv_technology",
                            "value" => $this->_config->technologyTvVo
                        ],
                        [
                            "name"  => "tipo_servicio",
                            "value" => $this->_config->serviceTypeVoCorp
                        ],
                        [
                            "name" => "client_name",
                            "value" => $info["vo"]["name"]
                        ],
                    ],
                ]
            ];
            // Se consume el metodo de creacion de Elemento
            $response = $this->_http->request("POST", $url, $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 201) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }

        return $retorno;
    }

    /**
     * dropElemento
     *
     * @param  mixed $info
     * @return bol
     */
    public function dropElemento($info)
    {
        $retorno = TRUE;

        //Se evalua la respuesta
        $rsElemento = $this->getElemento($info["vo"]["mac"]);
        // Si retorna TRUE es porque encontro el elemento
        // por lo tanto se puede eliminar
        if ($rsElemento === TRUE) {
            // Si el elemento se encontro, se envia la eliminación del
            // elemento, si no se puede eliminar se retorna FALSE
            $respuesta = $this->deleteElemento($info["vo"]["mac"]);
            if ($respuesta === FALSE) {
                $retorno = FALSE;
            }
        }
        return $retorno;
    }

    /**
     * dropServicio
     *
     * @param  mixed $info
     * @return bol
     */
    public function dropServicio($info)
    {
        $retorno = TRUE;

        //Se evalua la respuesta
        $rsServicio = $this->getServicio($info["vo"]["serviceCode"]);
        // Si retorna TRUE es porque encontro el servicio
        // por lo tanto se puede eliminar
        if ($rsServicio === TRUE) {
            // Si el servicio se encontro, se envia la eliminación del
            // servicio, si no se puede eliminar se retorna FALSE
            $respuesta = $this->deleteServicio($info["vo"]["serviceCode"]);
            if ($respuesta === FALSE) {
                $retorno = FALSE;
            }
        }
        return $retorno;
    }

    /**
     * deleteElemento
     *
     * @param  mixed $serial
     * @return void
     */
    public function deleteElemento($serial)
    {
        $retorno = FALSE;
        // Se elimina el elemento
        try {
            $url = "/api/element/" . $serial;
            $response = $this->_http->request("DELETE", $url);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 204) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }
        return $retorno;
    }

    /**
     * deleteServicio
     *
     * @param  mixed $serviceCode
     * @return void
     */
    public function deleteServicio($serviceCode)
    {
        $retorno = FALSE;
        // Se elimina el servicio
        try {
            $url = "/api/service/" . $serviceCode;
            $response = $this->_http->request("DELETE", $url);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 204) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }
        return $retorno;
    }

    /**
     * getCliente
     *
     * @param  mixed $rut
     * @return void
     */
    public function getCliente($rut)
    {
        $retorno = FALSE;
        // Se obtiene la información relacionada al plan
        try {
            $url = "/api/client/" . $rut;
            $response = $this->_http->request("GET", $url);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 200) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }
        return $retorno;
    }

    /**
     * getElemento
     *
     * @param  mixed $serial
     * @return void
     */
    public function getElemento($serial)
    {
        $retorno = FALSE;
        // Se obtiene la información relacionada al plan
        try {
            $url = "/api/element/" . $serial;
            $response = $this->_http->request("GET", $url);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 200) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }
        return $retorno;
    }

    /**
     * getPlan
     *
     * @param  mixed $code
     * @return void
     */
    public function getPlan($code)
    {
        $retorno = FALSE;
        // Se obtiene la información relacionada al plan
        try {
            $url = "/api/plan/" . $code;
            $response = $this->_http->request("GET",$url);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if((int)$status==200) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }
        return $retorno;
    }

    /**
     * getResponse
     *
     * @return void
     */
    public function getResponseApi()
    {
        $response = $this->_response;
        return $response;
    }

    /**
     * getServicio
     *
     * @param  mixed $serviceCode
     * @return void
     */
    public function getServicio($serviceCode)
    {
        $retorno = FALSE;
        // Se obtiene la información relacionada al plan
        try {
            $url = "/api/service/" . $serviceCode;
            $response = $this->_http->request("GET", $url);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 200) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }
        return $retorno;
    }

    /**
     * resumeServicio
     *
     * @param  mixed $info
     * @return bol
     */
    public function resumeServicio($info)
    {
        $retorno = TRUE;

        //Se evalua la respuesta
        $rsServicio = $this->getServicio($info["vo"]["serviceCode"]);
        // Si retorna TRUE es porque encontro el servicio
        // por lo tanto se puede activar
        if ($rsServicio === TRUE) {
            // Si el servicio se encontro, se envia la activavion del
            // servicio, si no se puede activar se retorna FALSE
            $respuesta = $this->updateActiveServicio($info["vo"]["serviceCode"]);
            if ($respuesta === FALSE) {
                $retorno = FALSE;
            }
        }
        return $retorno;
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
            case "PLATAFORMA":
                // Genera estructura de retorno de error
                $str = "PLATAFORMA VO NO ESTA ACTIVA EN MEDIADOR";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "CONFIGURACION";
                $error["error"]["faultActor"]   = "MEDIADOR";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "URL":
                // Genera estructura de retorno de error
                $str = "URL DEL WS DE VO NO ESTA RESPONDIENDO";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "API";
                $error["error"]["faultActor"]   = "VO";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "TOKEN":
                // Genera estructura de retorno de error
                $str = "TOKEN NO PUDO SER GENERADO EN WS VO";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "API";
                $error["error"]["faultActor"]   = "VO";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "PLAN":
                // Genera estructura de retorno de error
                $str = "NO SE ENCONTRO EL PLAN EN VO";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "API";
                $error["error"]["faultActor"]   = "VO";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "CLIENTE":
                // Genera estructura de retorno de error
                $str = "OCURRIO UN ERROR EN LA ASIGNACION DE CLIENTE EN VO";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "API";
                $error["error"]["faultActor"]   = "VO";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "SERVICIO":
                // Genera estructura de retorno de error
                $str = "OCURRIO UN ERROR EN LA CREACION DEL SERVICIO EN VO";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "API";
                $error["error"]["faultActor"]   = "VO";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "ELEMENTO":
                // Genera estructura de retorno de error
                $str = "OCURRIO UN ERROR EN LA CREACION DEL ELEMENTO EN VO";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "API";
                $error["error"]["faultActor"]   = "VO";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
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
     * setInstanceDb
     *
     * @param  mixed $db
     * @return void
     */
    public function setInstanceDb($db)
    {
        if (!$this->_dbMediador) {
            $this->_dbMediador = $db;
        }
        $this->_sqlMediador = new Sql($this->_dbMediador);
    }

    /**
     * setCliente
     *
     * @param  mixed $info
     * @return void
     */
    public function setCliente($info)
    {
        $retorno = TRUE;

        //Se verifica si el cliente existe
        $rsCliente = $this->getCliente($info["vo"]["rut"]);
        // Si retorna FALSE es porque no encontro el cliente
        // por lo tanto se puede crear el cliente
        if ($rsCliente === FALSE) {
            // Se envia a crear el cliente, si no se puede crear
            // se retorna FALSE
            $respuesta = $this->createCliente($info);
            if ($respuesta === FALSE) {
                $retorno = FALSE;
            }
        }
        return $retorno;
    }

    /**
     * setElementos
     *
     * @param  mixed $info
     * @return bol
     */
    public function setElementos($info)
    {
        $retorno = TRUE;
        // Obtiene todos los stbs, para conocer el Modelo y Marca
        $stb = $this->_sqlMediador->getStbAll();
        // Se recorren todos los elementos y se crea uno por uno
        foreach($info["vo"]["series"] as $key=>$value) {
                       //Se evalua la respuesta
            $rsElemento = $this->getElemento($info["vo"]["macs"][$key]);
            // Si retorna FALSE es porque no encontro el elemento
            // por lo tanto se puede crear el elemento
            if ($rsElemento === FALSE) {
                // Se envia a crear el elemento, si no se puede crear
                // se retorna FALSE
                $respuesta = $this->createElemento($info, $stb, $key, $value);
                if ($respuesta === FALSE) {
                    $retorno = FALSE;
                }
            } else{
                // Si el elemento se encontro, se debe eliminar y proceder a
                // crearlo nuevamente
                // si no se puede crear se retorna FALSE
                $respuesta = $this->updateElemento($info, $stb, $key, $value);
                if ($respuesta === FALSE) {
                    $retorno = FALSE;
                }
            }
        }
        return $retorno;
    }

    /**
     * setServicios
     *
     * @param  mixed $info
     * @return bol
     */
    public function setServicio($info)
    {
        $retorno = TRUE;

        //Se evalua la respuesta
        $rsServicio = $this->getServicio($info["vo"]["serviceCode"]);
        // Si retorna FALSE es porque no encontro el servicio
        // por lo tanto se puede crear el servicio
        if ($rsServicio === FALSE) {
            // Se envia a crear el servicio, si no se puede crear
            // se retorna FALSE
            $respuesta = $this->createServicio($info);
            if ($respuesta === FALSE) {
                $retorno = FALSE;
            }
        } else {
            // Si el servicio se encontro, se envia una actualizacion del
            // servicio, en este caso no se vuelve a crear, solo actualizar
            // si no se puede crear se retorna FALSE
            $respuesta = $this->updateServicio($info);
            if ($respuesta === FALSE) {
                $retorno = FALSE;
            }
        }
        return $retorno;
    }

    /**
     * setServicioCorp
     *
     * @param  mixed $info
     * @return bol
     */
    public function setServicioCorp($info)
    {
        $retorno = TRUE;

        //Se evalua la respuesta
        $rsServicio = $this->getServicio($info["vo"]["serviceCode"]);
        // Si retorna FALSE es porque no encontro el servicio
        // por lo tanto se puede crear el servicio
        if ($rsServicio === FALSE) {
            // Se envia a crear el servicio, si no se puede crear
            // se retorna FALSE
            $respuesta = $this->createServicioCorp($info);
            if ($respuesta === FALSE) {
                $retorno = FALSE;
            }
        } else {
            // Si el servicio se encontro, se envia una actualizacion del
            // servicio, en este caso no se vuelve a crear, solo actualizar
            // si no se puede crear se retorna FALSE
            $respuesta = $this->updateServicioCorp($info);
            if ($respuesta === FALSE) {
                $retorno = FALSE;
            }
        }
        return $retorno;
    }

    /**
     * setServicios
     *
     * @param  mixed $info
     * @return bol
     */
    public function setServicioCambioPlan($info)
    {
        $retorno = TRUE;

        //Se evalua la respuesta
        $rsServicio = $this->getServicio($info["vo"]["serviceCode"]);
        // Si retorna FALSE es porque no encontro el servicio
        if ($rsServicio === FALSE) {
            $retorno = FALSE;
        }else{
            // Si el servicio se encontro, se envia una actualizacion del
            // servicio, en este caso es una actualización parcial del
            // servicio, porque solo se van a cambiar los planes
            $respuesta = $this->updateServicioCambioPlan($info);
            if ($respuesta === FALSE) {
                $retorno = FALSE;
            }
        }
        return $retorno;
    }

    /**
     * setToken
     *
     * @return void
     */
    public function setToken()
    {
        // Obtiene el Token de autenticación Oauth2
        $this->_accessToken = $this->_getToken();
        // Obtiene la instancia de conexion al API REST de VO
        $this->_http        = $this->_getHttp();

        if($this->_accessToken !== "") {
            return TRUE;
        } else {
            return FALSE;
        }

    }

    /**
     * _setResponse
     *
     * @return void
     */
    private function _setResponse($response)
    {
        // Se captura el cuerpo de la respuesta del WS, se descompone y valida
        // para ser retornada al final del proceso para evidenciar casos de
        // error
        $contents       = (string) $response->getBody();
        $responseJson   = json_decode($contents, TRUE);
        $message        = preg_replace('([^A-Za-z0-9 !:-])', '', $responseJson["message"]);
        $code           = $responseJson["code"];
        $dataJson = [
            "message" => $message,
            "code"    => $code,
        ];
        // Se arma estructrua de respuestaq del Ws
        $this->_response = $dataJson;
    }

    /**
     * suspendServicio
     *
     * @param  mixed $info
     * @return bol
     */
    public function suspendServicio($info)
    {
        $retorno = TRUE;

        //Se evalua la respuesta
        $rsServicio = $this->getServicio($info["vo"]["serviceCode"]);
        // Si retorna TRUE es porque encontro el servicio
        // por lo tanto se puede suspender
        if ($rsServicio === TRUE) {
            // Si el servicio se encontro, se envia la suspencion del
            // servicio, si no se puede suspender se retorna FALSE
            $respuesta = $this->updateInactiveServicio($info["vo"]["serviceCode"]);
            if ($respuesta === FALSE) {
                $retorno = FALSE;
            }
        }
        return $retorno;
    }

    /**
     * updateElemento
     *
     * @param  mixed $info
     * @param  mixed $stb
     * @param  mixed $key
     * @param  mixed $serie
     *
     * @return void
     */
    public function updateElemento($info, $stb, $key, $serie)
    {
        $retorno = FALSE;
        // Se obtiene el prefijo de la serie para obtener modelo y marca
        $prefijo = $info["vo"]["prefijoSeries"][$serie];
        $tipo = $this->_config->elementTypeVo;
        $compania = $this->_config->tenantVo;
        try {
            $url = "/api/element/" . $info["vo"]["macs"][$key];
            $data = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'json' => [
                    'serial'       => $info["vo"]["macs"][$key],
                    'name'         => $info["vo"]["series"][$key],
                    'manufacturer' => $stb[$prefijo]["marca"],
                    'model'        => $stb[$prefijo]["modelo"],
                    'elementType'  => [
                        $tipo => $tipo
                    ],
                    'tenant'       => [
                        $compania => $compania
                    ]
                ]
            ];
            // Se consume el metodo de creacion de Elemento
            $response = $this->_http->request("PUT", $url, $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 200) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }

        return $retorno;
    }

    /**
     * updateActiveServicio
     *
     * @param  mixed $info
     *
     * @return void
     */
    public function updateActiveServicio($serviceCode)
    {
        $retorno = FALSE;
        try {
            $url = "/api/service/" . $serviceCode . "/resume";
            $data = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'json' => [
                    'type'       => $this->_config->serviceTypeVo,
                    'status'     => 1
                ]
            ];
            // Se consume el metodo de update de servicio
            $response = $this->_http->request("PUT", $url, $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 200) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }

        return $retorno;
    }

    /**
     * updateInactiveServicio
     *
     * @param  mixed $info
     *
     * @return void
     */
    public function updateInactiveServicio($serviceCode)
    {
        $retorno = FALSE;
        try {
            $url = "/api/service/" . $serviceCode . "/suspend";
            $data = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'json' => [
                    'type'       => $this->_config->serviceTypeVo,
                    'status'     => 0
                ]
            ];
            // Se consume el metodo de update de servicio
            $response = $this->_http->request("PUT", $url, $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 200) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }

        return $retorno;
    }

    /**
     * updateServicio
     *
     * @param  mixed $info
     * @return void
     */
    public function updateServicio($info)
    {
        $retorno = FALSE;
        $planes = $info["vo"]["planes"];
        // Se elimina la primera posicion para los package
        unset($planes[0]);
        // Se unifican los planes en un string separado por comas
        $package = implode(",", $planes);
        try {
            $url = "/api/service/". $info["vo"]["serviceCode"];
            $data = [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'type'          => $this->_config->typeVo,
                    'servicecode'   => $info["vo"]["serviceCode"],
                    'rut'           => $info["vo"]["rut"],
                    'packetcode'    => $info["vo"]["planes"][0],
                    'elements'      => $info["vo"]["macs"],
                    'parameters'    => [
                        [
                            "name"  => "caller_id_number",
                            "value" => ""
                        ],
                        [
                            "name"  => "additional_packages",
                            "value" => $package
                        ],
                        [
                            "name"  => "region",
                            "value" => $info["vo"]["codigoRegion"]
                        ],
                        [
                            "name"  => "amount_stb",
                            "value" => $info["vo"]["stbs"]
                        ],
                        [
                            "name"  => "tipo_acceso",
                            "value" => $this->_config->accessTypeVo
                        ],
                        [
                            "name"  => "compania",
                            "value" => $this->_config->tenantVo
                        ],
                        [
                            "name"  => "provision",
                            "value" => $this->_config->provisionVo
                        ],
                        [
                            "name"  => "tv_technology",
                            "value" => $this->_config->technologyTvVo
                        ],
                        [
                            "name"  => "tipo_servicio",
                            "value" => $this->_config->serviceTypeVo
                        ],
                        [
                            "name" => "client_name",
                            "value" => $info["vo"]["name"]
                        ],
                    ],
                ]
            ];
            // Se consume el metodo de creacion de Elemento
            $response = $this->_http->request("PUT", $url, $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 200) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }

        return $retorno;
    }

    /**
     * updateServicioCorp
     *
     * @param  mixed $info
     * @return void
     */
    public function updateServicioCorp($info)
    {
        $retorno = FALSE;
        $planes = $info["vo"]["planes"];
        // Se elimina la primera posicion para los package
        unset($planes[0]);
        // Se unifican los planes en un string separado por comas
        $package = implode(",", $planes);
        if (trim($package) === "") {
            $package = $info["vo"]["planes"][0];
        }
        try {
            $url = "/api/service/" . $info["vo"]["serviceCode"];
            $data = [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'type'          => $this->_config->typeVo,
                    'servicecode'   => $info["vo"]["serviceCode"],
                    'rut'           => $info["vo"]["rut"],
                    'packetcode'    => $info["vo"]["planes"][0],
                    'elements'      => "",
                    'parameters'    => [
                        [
                            "name"  => "caller_id_number",
                            "value" => ""
                        ],
                        [
                            "name"  => "additional_packages",
                            "value" => $package
                        ],
                        [
                            "name"  => "region",
                            "value" => $info["vo"]["codigoRegion"]
                        ],
                        [
                            "name"  => "amount_stb",
                            "value" => $info["vo"]["stbs"]
                        ],
                        [
                            "name"  => "compania",
                            "value" => $this->_config->tenantVo
                        ],
                        [
                            "name"  => "provision",
                            "value" => $this->_config->provisionVo
                        ],
                        [
                            "name"  => "tv_technology",
                            "value" => $this->_config->technologyTvVo
                        ],
                        [
                            "name"  => "tipo_servicio",
                            "value" => $this->_config->serviceTypeVoCorp
                        ],
                        [
                            "name" => "client_name",
                            "value" => $info["vo"]["name"]
                        ],
                    ],
                ]
            ];
            // Se consume el metodo de creacion de Elemento
            $response = $this->_http->request("PUT", $url, $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 200) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }

        return $retorno;
    }

    /**
     * updateServicio2
     *
     * @param  mixed $info
     * @return void
     */
    public function updateServicio2($info)
    {
        $retorno = FALSE;
        $planes = $info["vo"]["planes"];
        // Se elimina la primera posicion para los package
        unset($planes[0]);
        // Se unifican los planes en un string separado por comas
        $package = implode(",", $planes);
        try {
            $url = "/api/service/" . $info["vo"]["serviceCode"];
            $data = [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'type'          => $this->_config->typeVo,
                    'serviceid'     => $info["vo"]["serviceId"],
                    'servicecode'   => $info["vo"]["serviceCode"],
                    'rut'           => $info["vo"]["rut"],
                    'packetcode'    => $info["vo"]["planes"][0],
                    'elements'      => $info["vo"]["series"],
                    'parameters'    => [
                        [
                            "name"  => "caller_id_number",
                            "value" => ""
                        ],
                        [
                            "name"  => "additional_packages",
                            "value" => $package
                        ],
                        [
                            "name"  => "region",
                            "value" => $info["vo"]["codigoRegion"]
                        ],
                        [
                            "name"  => "amount_stb",
                            "value" => $info["vo"]["stbs"]
                        ],
                        [
                            "name"  => "service_order",
                            "value" => $info["vo"]["serviceOrder"]
                        ],
                        [
                            "name"  => "tipo_acceso",
                            "value" => $this->_config->accessTypeVo
                        ],
                        [
                            "name"  => "ftth_port",
                            "value" => $info["vo"]["ftthPort"]
                        ],
                        [
                            "name"  => "ftth_nodo",
                            "value" => $info["vo"]["ftthNode"]
                        ],
                        [
                            "name"  => "ftth_equipo",
                            "value" => $info["vo"]["ftthEquipo"]
                        ],
                        [
                            "name"  => "compania",
                            "value" => $this->_config->tenantVo
                        ],
                        [
                            "name"  => "provision",
                            "value" => $this->_config->provisionVo
                        ],
                        [
                            "name"  => "tv_technology",
                            "value" => $this->_config->technologyTvVo
                        ],
                        [
                            "name"  => "tipo_servicio",
                            "value" => $this->_config->serviceTypeVo
                        ],
                        [
                            "name" => "client_name",
                            "value" => $info["vo"]["name"]
                        ],
                    ],
                ]
            ];
            // Se consume el metodo de creacion de Elemento
            $response = $this->_http->request("PUT", $url, $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 200) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }

        return $retorno;
    }

    /**
     * updateServicioCambioPlan
     *
     * @param  mixed $info
     * @return void
     */
    public function updateServicioCambioPlan($info)
    {
        $retorno = FALSE;
        $planes = $info["vo"]["planes"];
        // Se elimina la primera posicion para los package
        unset($planes[0]);
        // Se unifican los planes en un string separado por comas
        $package = implode(",", $planes);
        try {
            $url = "/api/service/" . $info["vo"]["serviceCode"];
            $data = [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'type'          => $this->_config->typeVo,
                    'rut'           => $info["vo"]["rut"],
                    'packetcode'    => $info["vo"]["planes"][0],
                    'parameters'    => [
                        [
                            "name"  => "additional_packages",
                            "value" => $package
                        ],
                        [
                            "name"  => "tipo_acceso",
                            "value" => $this->_config->accessTypeVo
                        ],
                        [
                            "name"  => "compania",
                            "value" => $this->_config->tenantVo
                        ],
                        [
                            "name"  => "provision",
                            "value" => $this->_config->provisionVo
                        ],
                        [
                            "name"  => "tv_technology",
                            "value" => $this->_config->technologyTvVo
                        ],
                        [
                            "name"  => "tipo_servicio",
                            "value" => $this->_config->serviceTypeVo
                        ],
                    ],
                ]
            ];
            // Se consume el metodo de creacion de Elemento
            $response = $this->_http->request("PUT", $url, $data);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
        }
        //Se evalua la respuesta
        $status = $response->getStatusCode();
        if ((int) $status == 200) {
            $retorno = TRUE;
        } else {
            $this->_setResponse($response);
        }

        return $retorno;
    }

    /**
     * validatePlanes
     *
     * @param  mixed $info
     * @return bol
     */
    public function validatePlanes($info)
    {
        $retorno = TRUE;
        // Se recorren todos los planes para verificar si estan activos
        foreach ($info["vo"]["planes"] as $key => $value) {
                       //Se evalua la respuesta
            $rsPlan = $this->getPlan($value);
            // Si retorna FALSE es porque no encontro el elemento
            if ($rsPlan === FALSE) {
                $retorno = FALSE;
            }
        }
        return $retorno;
    }
}

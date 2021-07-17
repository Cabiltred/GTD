<?php

/**
 * Web Service de Corte y Reposicion para  Minerva y VO
 *
 * Este Webservice esta destinado para el corte y reposicion de servicio
 * de GTD y Telsur haciendo uso del Midleware Minerva (SOAP) y VO (REST)
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
 * @package Webservice
 * @author Macarena Cerda Mora      <mcerda@grupogtd.com>
 * @author Franciso Sandoval Iturra <Francisco.Sandoval@grupogtd.com>
 * @copyright 2020 GTD
 * @license https://opensource.org/licenses/MIT MIT License
 * @link http://localhost/
 *
 */

require_once __DIR__ . '/vendor/autoload.php';
// Usa las constantes definidas para la aplicación
use Gtd\ClasesTransversal\Config;
use Aw\Nusoap\NusoapServer;
use Aw\Nusoap\NusoapFault;
use Gtd\ClasesWs\TvCorteServicio;
use Gtd\ClasesWs\TvRepoServicio;

/**
 * En el namespace Gtd\ClasesTransversal\Config se encuentra la clase para
 * configurar las variables de la aplicación, se tiene para cada ambiente su
 * correspondiente metodo, en el constructor puede cambiar el valor por
 * defecto del ambiente en el que se encuentra la aplicacion
 *
 * public function __construct($ambiente="DEV")
 *
 * Las posibles opciones son
 *
 * DEV = Desarrollo
 * QA  = Pruebas
 * PRD = Produccion
 */

$config = new Config();

// Se define el servidor y su espacio de nombres
$wsURL = $config->urlWsdl . $config->folderWsdl . "ws_tv_corte_y_repo.php";
$server = new NusoapServer();
// $server->debug_flag=false;
$server->configurewsdl('ws_tv_corte_y_repo', $wsURL);
$server->wsdl->schematargetnamespace = $wsURL;

/**
 * Metodo ws_tv_corte
 *
 * @Param string $instancia
 *        Varchar(100)
 *        Instancia Sicret
 *        EJemplo: TV10000
 * @Param $rut rut del cliente
 *        Varchar(200)
 *        Identificacion del cliente
 *        Ejemplo:12345
 *
 * @return Struct
 */

function ws_tv_corte(
    $instancia,
    $rut
)
{

    // Se arma la estructura que llevara todos los atributos recogidos por el
    // Webserive a la Clase que dara el corto y reposicion del servicio
    $info = array(
        "instancia" => $instancia,
        "rut"       => $rut,
        "planes"    => "n/a",
    );

    // Se instancia la clase de TvBajaServicio
    $obj = new TvCorteServicio($info);
    // Se llama metodo que da de alta el servicio en Minerva
    $resultado = $obj->corteServicio();
    if ($resultado["default"]["status"] === FALSE) {
        return new NusoapFault(
            $resultado["default"]["error"]["faultCode"],
            $resultado["default"]["error"]["faultActor"],
            $resultado["default"]["error"]["faultString"],
            $resultado["default"]["error"]["faultDetail"]
        );
    }else{
        // Aqui se unen los resultados de cada plataforma para retornar
        return $resultado["default"]["respuesta"];
    }

    // Se retorna el resultado en formato SOAP
}

/**
 * Metodo ws_tv_reposicion
 *
 * @Param string $instancia
 *        Varchar(100)
 *        Instancia Sicret
 *        EJemplo: TV10000
 * @Param $rut rut del cliente
 *        Varchar(200)
 *        Identificacion del cliente
 *        Ejemplo:12345
 * @Param $planes
 *        Varchar(200)
 *        Planes a procesar separados por "|" con fecha de alta y
 *        fecha de baja
 *        Ejemplo: '10-07-2014',,79|1|35,'10-07-2014','01-11-2014',35
 *
 * @return Struct
 */

function ws_tv_reposicion(
  $instancia,
  $rut,
  $planes
)
{

    // Se arma la estructura que llevara todos los atributos recogidos por el
    // Webserive a la Clase que dara reposicion del servicio
    $info = array(
        "instancia" => $instancia,
        "rut"       => $rut,
        "planes"    => $planes
    );

    // Se instancia la clase de TvBajaServicio
    $obj = new TvRepoServicio($info);
    // Se llama metodo que da de alta el servicio en Minerva
    $resultado = $obj->repoServicio();
    if ($resultado["default"]["status"] === FALSE) {
        return new NusoapFault(
            $resultado["default"]["error"]["faultCode"],
            $resultado["default"]["error"]["faultActor"],
            $resultado["default"]["error"]["faultString"],
            $resultado["default"]["error"]["faultDetail"]
        );
    } else {
        // Aqui se unen los resultados de cada plataforma para retornar
        return $resultado["default"]["respuesta"];
    }

    // Se retorna el resultado en formato SOAP
}

//Para el metodo ws_tv_corte
$parametersCorte = array(
  'instancia' => 'xsd:string',
  'rut'       => 'xsd:string'
);

//Para el metodo ws_tv_reposicion
$parametersRepo = array(
  'instancia' => 'xsd:string',
  'rut'       => 'xsd:string',
  'planes'    => 'xsd:string'
);

$server->wsdl->addComplexType(
  'resultado',
  'complexType',
  'struct',
  'all',
  '',
  array(
    'estado_minerva' => array(
      'name' => 'estado_minerva',
      'type' => 'xsd:string'
    ),
    'estado_vo' => array(
      'name' => 'estado_vo',
      'type' => 'xsd:string'
    ),
    'mensaje_minerva' => array(
      'name' => 'mensaje_minerva',
      'type' => 'xsd:string'
    ),
    'mensaje_vo' => array(
      'name' => 'mensaje_vo',
      'type' => 'xsd:string'
    ),
  )
); //datos de salida

$server->register(
  'ws_tv_corte', //nombre de la funcion
  $parametersCorte, //parametros de entrada
  array('return' => 'tns:resultado'),
  $wsURL
);

$server->register(
  'ws_tv_reposicion', //nombre de la funcion
  $parametersRepo, //parametros de entrada
  array('return' => 'tns:resultado'),
  $wsURL
);

// Se setea $HTTP_RAW_POST_DATA para que PHP no le de formato al mensaje
// y lo envíe tal cual se genera
if (!isset($HTTP_RAW_POST_DATA)) {
    $HTTP_RAW_POST_DATA = file_get_contents('php://input');
}

//Se publica el servicio
$server->service($HTTP_RAW_POST_DATA);

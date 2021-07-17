<?php

/**
 * Web Service de Baja de Deco para Minerva y VO
 *
 * Este Webservice esta destinado para dar de baja el Deco de
 * un servicio de GTD y Telsur
 * haciendo uso del Midleware Minerva (SOAP) y VO (REST)
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
use Gtd\ClasesWs\TvDelStbCorp;

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
$wsURL = $config->urlWsdl . $config->folderWsdl . "ws_tv_del_stb_corp.php";
$server = new NusoapServer();
// $server->debug_flag=false;
$server->configurewsdl('ws_tv_del_stb_corp', $wsURL);
$server->wsdl->schematargetnamespace = $wsURL;

/**
 * Metodo delStb
 *
 * @Param string $instancia
 *        Varchar(100)
 *        Instancia Sicret
 *        EJemplo: TV10000
 * @Param $mac la direcciond el dispositivo
 *        Varchar(200)
 *        Direcciones Mac de decodificadores a bajar del servicio
 *        Ejemplo: E091532153B0
 *
 * @return Struct
 */

function delStb(
    $instancia,
    $mac
)
{

    // Se arma la estructura que llevara todos los atributos recogidos por el
    // Webserive a la Clase que dara de baja el deco
    $info = array(
        "instancia" => $instancia,
        "mac"      => $mac
    );

    // Se instancia la clase de TvDelStbCorp
    $obj = new TvDelStbCorp($info);
    // Se llama metodo que da de baja el deco del servicio en Minerva
    $resultado = $obj->delStb();
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
//Para el metodo delStb
$parameters = array(
  'instancia' => 'xsd:string',
  'mac'       => 'xsd:string'
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
  'delStb', //nombre de la funcion
    $parameters, //parametros de entrada
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

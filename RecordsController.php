<?php
App::uses('AppController', 'Controller');

/**
 * Records Controller
 *
 * @property Record $Record
 * @property PaginatorComponent $Paginator
 */
class RecordsController extends AppController
{

    /**
     * Components
     *
     * @var array
     */
    public $components = array('Paginator', 'FTPConnection', 'Cookie');
//    public $paginate = array('search' => 'search');
    public $uses = array('Record', 'Server', 'Extension');
    private $limit = 1000;

    /**
     * index method
     *
     * @return void
     */

    public function beforeFilter()
    {
        parent::beforeFilter();
        $this->Auth->allow('search');
    }

    /**
     * delete method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    //funcion que prepara la consulta de la busqueda de la palabra y la de la cuenta
//de los servidores que tenian resultados para la palabra buscada
    private function orderFiltersRequest($array)
    {
        $data = array();
        if (!empty($array)) {
            foreach ($array as $array) {
                $data[0][] = array("Record.name LIKE " => '%.' . $array['Extension']['value']);
                if (empty($data[1])) {
                    $data[1] = 'records.name LIKE ' . '"%.' . $array['Extension']['value'] . '"';
                } else {
                    $data[1] = $data[1] . ' OR records.name LIKE ' . '"%.' . $array['Extension']['value'] . '"';
                }
            }
        }
        return $data;
    }

    private function recordsPaginate($textToSearch, $filter, $in, $searchIn)
    {
        $this->paginate = array('search');
        $returnVar['records'] = '';
        $returnVar['text'] = '';
        $var = $this->paginate(array('words' => $textToSearch, 'limit' => $this->limit, 'filters' => $filter, 'in' => $in, 'servers' => $searchIn, 'queryInfo' => false));
        //Si se obtuvieron resultados para la consulta realizada
        if (!empty($var)) {
            $returnVar['records'] = $var;
            $returnVar['text'] = $textToSearch;
            return $returnVar;
        } else {
            //Sino se obtuvieron resultados y el texto seleccionado tiene mas de una palabra
            if (strpos($textToSearch, ' ') > 0) {
                $words = explode(' ', $_GET['searcherWord']);
                $countOfWords = count($words);
                $pointer = 0;
                while ($pointer < $countOfWords ) {
                    if(!empty($words[$pointer]) & strlen($words[$pointer]) > 3){
                        $returnVar['records'] = $this->paginate(array('words' => $words[$pointer], 'filters' => $filter, 'in' => $in, 'servers' => $searchIn, 'queryInfo' => false));
                        if (!empty($returnVar['records'])) {
                            break;
                        } else {
                            $pointer++;
                        }
                    }else{
                        $pointer++;
                    }

                }
                if (!empty($returnVar['records'])) {
                    $this->Session->setFlash('No hemos podido encontrar ningun resultado para el texto "<b class="text-danger">' . $textToSearch . '</b>"' . ' en su lugar hemos encontrado resultados para la palabra "<em class="text-success"><b>' . $words[$pointer] . '</b></em>".', 'alert_search_warning');
                    $returnVar['text'] = $words[$pointer];
                }
            }
        }
        return $returnVar;
    }

    public function search()
    {
        if ($this->request->is('get')) {
            //Tiempo inicial para calcular el tiempo de la consulta
            $timeStart = strtotime('now');

            if (isset($_GET['searcherWord']) && !empty($_GET['searcherWord'])) {
                $textToSearch = $_GET['searcherWord'];
                //variable que controla si se calcula la informacion sobre la consulta o no
                $infoResults = false;
                if (isset($_GET['info']) && $_GET['info'] == 't') {
                    $infoResults = true;
                }
                //Variable que controla si hay que buscar en un ip determinado nada mas
                $searchIn = '';
                if ($this->Cookie->check('servers')) {
                    $searchIn = $this->Cookie->read('servers');
                    if (!empty($searchIn)) {
                        if ($searchIn[0] == 'all') {
                            $this->Cookie->delete('servers');
                            $searchIn = null;
                        }
                    }
                }
                //Mandando el valor de con que se tiene que comparar en la busqueda
                if (isset($_GET['in']) && !empty($_GET['in'])) {
                    $in = $_GET['in'];
                    $this->set('in', $_GET['in']);
                } else {
                    $in = 'name';
                    $this->set('in', 'name');
                }

                //Opteniendo el filtro seleccionado
                $filter = null;
                if (isset($_GET['filter']) && !empty($_GET['filter'])) {
                    $filter = $_GET['filter'];
                }

            } else {
                //si no se mando ninguna palabra para buscar se manda vacio
                $this->set('records', null);
                return $this->render();
            }
            //Consulta para saber la cantidad de resultados por servidores
            //y prueba la coneccion con cada uno de ellos
            $statesServers = '';
            //Agreganodo el filtro de los directorios
            $extensions='';
            if ($filter !== null && $filter !== 'all' && $filter !== 'others') {
                $extensions = $this->Extension->find('all', array('conditions' => array('Format.name' => $_GET['filter']), 'fields' => 'Extension.value'));
            } else {
                if ($filter === 'others') {
                    $extensions['NOT'] = $this->Extension->find('all', array('fields' => 'Extension.value'));
                }
            }
            //Si no se encontro ninguna extension para el filtro seleccionado se busca sin filtro
            //y se muestra uun mensage informandoselo al usuario
            if(empty($extensions) && $filter!=='all'){
                $this->Session->setFlash('Lo sentimos pero no encontramos ninguna extension registrada en la BD para el formato seleccionado y los resultados que se muestran no tienen aplicado ningun formato. Contacte con el administrador para que lo agregue.', 'alert_search_info');
            }
            //Obteniendo los resultados con todas las condisiones
            $varReturn = $this->recordsPaginate($textToSearch, $extensions, $in, $searchIn);
            //Se muestra la informacion sobre los resultados si hay resultados que mostrar
            if ($infoResults) {
                if (!empty($varReturn['records'])) {
                    //Cuando la memoria del servidor es insuficiente.
                    try {
                        $countOfServers = $this->Record->find('search', array('conditions' => array('words' => $textToSearch, 'filters' => $filter, 'in' => $in, 'servers' => $searchIn, 'queryInfo' => true)));
                    } catch (Exception $ex) {
                        $this->Session->setFlash('Sorry, the server memory cant not allowed all the results fetched. Please be more specific in the files searcher,
                    or change where search in right side of the input where you enter the text to search.', 'alert_search_error');
                        return $this->redirect(array('controller' => 'records', 'action' => 'search', 'filter=' . $filter, 'searcherWord' . $varReturn['text'], 'in=' . $in));
                    }
                    //probando las conecciones a los ftps para saber si estan activos
                    foreach ($countOfServers as $ip => $count) {
                        $server = $this->Record->Server->find('all', array('conditions' => array('Server.i_p' => $ip), 'recursive' => 0))[0];
                        if ($this->FTPConnection->testConnection($server["Server"]['i_p'], $server["Server"]['user'], $server["Server"]['pass'])) {
                            $statesServers[] = array($ip, $server['Server']['name'], true);
                        } else {
                            $statesServers[] = array($ip, $server['Server']['name'], false);
                        }
                    }
                    $this->set('countOfServers', $countOfServers);
                    $this->set('statesServers', $statesServers);
                } else {
                    $this->Session->setFlash('Sorry, bot not info to show, because not result find.', 'alert_search_warning');
                    return $this->redirect(array('controller' => 'records', 'action' => 'search', 'filter=' . $filter, 'searcherWord' . $varReturn['text'], 'in=' . $in));
                }
            }

            $this->set('filter', $_GET['filter']);
            //Mostrando los resultados de la busqueda
            if (!empty($varReturn['records'])) {
                $this->set('wordSearched', $varReturn['text']);
                $this->set('records', $varReturn['records']);
            } else {
                $this->set('wordSearched', $textToSearch);
                $this->set('records', '');
                $this->Session->setFlash('Lo sentimos pero no tenemos resultados que mostrar para <em><b>'.$textToSearch.'</b></em>. Asegurese de que ha insertado la(s) palabra(s) corecta(s)', 'alert_search_warning');
            }
            //Calculando el tiempo de la consulta
            $timeEnd = strtotime('now');
            $diff = intval($timeEnd - $timeStart);
            $this->set('queryTime', $diff);
            $this->set('info', $infoResults);
            return $this->render();
        }
        return $this->render('pages/home');
    }
}
